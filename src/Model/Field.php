<?php

declare(strict_types=1);

namespace Phlex\Data\Model;

use Phlex\Core\InjectableTrait;
use Phlex\Core\OptionsTrait;
use Phlex\Core\TrackableTrait;
use Phlex\Core\Utils;
use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\MutatorInterface;

/**
 * @method Model|null getOwner()
 */
class Field
{
    use Field\TypeTrait;
    use InjectableTrait;
    use JoinLinkTrait;
    use Model\ElementTrait;
    use OptionsTrait;
    use TrackableTrait;

    public const PERSIST_NONE = 0;
    public const PERSIST_LOAD = 1;
    public const PERSIST_SAVE = 2;

    public $persist = self::PERSIST_SAVE | self::PERSIST_LOAD;

    /**
     * Default value of field.
     *
     * @var mixed
     */
    public $default;

    /**
     * Actual field name.
     *
     * @var string|null
     */
    public $actual;

    /**
     * Is it system field?
     * System fields will be always loaded and saved.
     *
     * @var bool
     */
    public $system = false;

    /**
     * Defines a label to go along with this field. Use getCaption() which
     * will always return meaningful label (even if caption is null). Set
     * this property to any string.
     *
     * @var string|null
     */
    public $caption;

    /**
     * Array with UI flags like editable, visible and hidden.
     *
     * @var array
     */
    public $ui = [];

    /**
     * Mandatory field must not be null. The value must be set, even if
     * it's an empty value.
     *
     * Can contain error message for UI.
     *
     * @var bool|string|null
     */
    public $mandatory = false;

    /**
     * Required field must have non-empty value. A null value is considered empty too.
     *
     * Can contain error message for UI.
     *
     * @var bool|string
     */
    public $required = false;

    /**
     * Defines if value can be set.
     *
     * @var bool
     */
    public $readOnly = false;

    /**
     * Constructor. You can pass field properties as array.
     */
    public function __construct(array $defaults = [])
    {
        foreach ($defaults as $key => $val) {
            if (is_array($val)) {
                $this->{$key} = array_replace_recursive(is_array($this->{$key} ?? null) ? $this->{$key} : [], $val);
            } else {
                $this->{$key} = $val;
            }
        }
    }

    protected function onHookShortToOwner(string $spot, \Closure $fx, array $args = [], int $priority = 5): int
    {
        $key = $this->getKey(); // use static function to allow this object to be GCed

        return $this->getOwner()->onHookDynamicShort(
            $spot,
            static fn (Model $owner) => $owner->getField($key),
            $fx,
            $args,
            $priority
        );
    }

    /**
     * Validate and normalize value.
     *
     * Depending on the type of a current field, this will perform
     * some normalization for strict types. This method must also make
     * sure that $f->required is respected when setting the value, e.g.
     * you can't set value to '' if type=string and required=true.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function normalize($value)
    {
        if (!$this->getOwner()->strict_types || $this->getOwner()->hook(Model::HOOK_NORMALIZE, [$this, $value]) === false) {
            return $value;
        }

        // NULL value is always fine if it is allowed
        if ($value === null || $value === '') {
            if ($this->required) {
                throw new Field\ValidationException([$this->elementName ?? $this->elementId => 'Must not be null or empty']);
            }
        }

        try {
            return $this->getValueType()->normalize($value);
        } catch (Field\Type\ValidationException $e) {
            throw new Field\ValidationException([$this->elementName ?? $this->elementId => $e->getMessage()]);
        }
    }

    /**
     * Casts field value to string.
     *
     * @param mixed $value Optional value
     */
    public function toString($value = null): ?string
    {
        return $this->getValueType()->toString($value ?? $this->get());
    }

    /**
     * Returns field value.
     *
     * @return mixed
     */
    public function get()
    {
        return $this->getOwner()->getEntry()->get($this->getKey(), $this->default);
    }

    /**
     * Sets field value.
     *
     * @param mixed $value
     */
    public function set($value): self
    {
        $key = $this->getKey();

        try {
            $value = $this->normalize($value);
        } catch (Exception $e) {
            throw $e
                ->addMoreInfo('key', $key)
                ->addMoreInfo('value', $value)
                ->addMoreInfo('field', $this);
        }

        $entry = $this->getOwner()->getEntry();

        // do nothing when value has not changed
        $currentValue = $entry->get($key, $this->default);
        if ($this->compare($value, $currentValue)) {
            return $this;
        }

        $this->assertNotReadOnly();

        // if value is same as loaded remove the dirty value, otherwise set
        if ($this->compare($value, $entry->getLoaded($key, $currentValue))) {
            $entry->reset($key, $currentValue);
        } else {
            $entry->set($key, $value);
        }

        return $this;
    }

    /**
     * Unset field value even if null value is not allowed.
     */
    public function setNull(): self
    {
        $this->getOwner()->setNull($this->getKey());

        return $this;
    }

    public function getKey(): string
    {
        return $this->elementId;
    }

    public function asPrimaryKey(): self
    {
        $model = $this->getOwner();

        if ($model->hasPrimaryKeyField() && !$this->isPrimaryKey()) {
            throw (new Exception('Model already has different primaryKey set'))
                ->addMoreInfo('existingPrimaryKey', $model->primaryKey)
                ->addMoreInfo('attemptedPrimaryKey', $this->getKey());
        }

        $model->primaryKey = $this->getKey();
        $this->required = true;
        $this->system = true;
        $this->readOnly = true;

        return $this;
    }

    public function setReadOnly(bool $value = true): self
    {
        $this->readOnly = $value;

        return $this;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    protected function assertNotReadOnly(): void
    {
        if ($this->readOnly) {
            throw (new Exception('Attempting to set value of readOnly field ' . $this->elementId))
                ->addMoreInfo('field', $this)
                ->addMoreInfo('model', $this->owner);
        }
    }

    /**
     * Compare new value of the field with existing one without retrieving.
     * In the trivial case it's same as ($value == $model->get($name)) but this method can be used for:
     *  - comparing values that can't be received - passwords, encrypted data
     *  - comparing images
     *  - if get() is expensive (e.g. retrieve object).
     *
     * @param mixed      $value
     * @param mixed|void $value2
     */
    public function compare($value, $value2 = null): bool
    {
//         if (func_num_args() === 1) {
//             $value2 = $this->get();
//         }

        // TODO code below is not nice, we want to replace it, the purpose of the code is simply to
        // compare if typecasted values are the same using strict comparison (===) or nor
        $typecastFunc = function ($v) {
            // do not typecast null values, because that implies calling normalize() which tries to validate that value can't be null in case field value is required
            if ($v === null) {
                return $v;
            }

            if ($persistence = $this->getPersistence()) {
                $persistenceValue = $persistence->encodeRow($this->getOwner(), [$this->getKey() => $v])[$this->getCodec($persistence)->getKey()];
            } else {
                // without persistence, we can not do a lot with non-scalar types, but as DateTime
                // is used often, fix the compare for them
                // TODO probably create and use a default persistence
                $persistenceValue = $this->normalize($v);
            }

            if (is_scalar($persistenceValue)) {
                return (string) $persistenceValue;
            } elseif ($persistenceValue instanceof \DateTimeInterface) {
                return $persistenceValue->getTimestamp() . '.' . $persistenceValue->format('u');
            }

            return $persistenceValue;
        };

        return $typecastFunc($value) === $typecastFunc($value2);
    }

    public function getQueryArguments($operator, $value): array
    {
        return $this->getCodec()->getQueryArguments($operator, $value);
    }

    public function getCodec(MutatorInterface $mutator = null): Field\Codec
    {
        return $this->getValueType()->createCodec($this, $mutator ?? $this->getPersistence());
    }

    public function encode($value, MutatorInterface $mutator = null)
    {
        return $this->getCodec($mutator)->encode($value);
    }

    public function decode($value, MutatorInterface $mutator = null)
    {
        return $this->getCodec($mutator)->decode($value);
    }

    public function isPrimaryKey(): bool
    {
        if (!$model = $this->getOwner()) {
            return false;
        }

        return $model->getPrimaryKeyField() === $this;
    }

    /**
     * Returns if field should be editable in UI.
     */
    public function isEditable(): bool
    {
        return $this->ui['editable'] ?? !$this->readOnly && $this->interactsWithPersistence() && !$this->system;
    }

    /**
     * Returns if field should be visible in UI.
     */
    public function isVisible(): bool
    {
        return $this->ui['visible'] ?? !$this->system;
    }

    /**
     * Returns if field should be hidden in UI.
     */
    public function isHidden(): bool
    {
        return $this->ui['hidden'] ?? false;
    }

    /**
     * Returns field caption for use in UI.
     */
    public function getCaption(): string
    {
        return $this->caption ?? $this->ui['caption'] ?? Utils::getReadableCaption($this->getKey());
    }

    public function setNeverPersist($value = true)
    {
        $this->persist = $value ? self::PERSIST_NONE : (self::PERSIST_LOAD | self::PERSIST_SAVE);

        return $this;
    }

    public function setNeverSave($value = true)
    {
        if ($value) {
            $this->persist &= ~self::PERSIST_SAVE;
        } else {
            $this->persist |= self::PERSIST_SAVE;
        }

        return $this;
    }

    public function loadsFromPersistence(): bool
    {
        return $this->checkPersisting(self::PERSIST_LOAD);
    }

    public function savesToPersistence(): bool
    {
        return $this->checkPersisting(self::PERSIST_SAVE);
    }

    public function interactsWithPersistence(): bool
    {
        return (bool) $this->persist;
    }

    public function checkPersisting(int $action): bool
    {
        return (bool) ($this->persist & $action);
    }

    public function getConditionValueTitle($value): ?string
    {
        return null;
    }

    /**
     * Set that field value should be unique.
     *
     * @return $this
     */
    public function setUnique()
    {
        $this->onHookShortToOwner(Model::HOOK_BEFORE_SAVE, function () {
            $owner = $this->getOwner();
            $key = $this->getKey();
            if ($owner->isDirty($key)) {
                $model = $owner->newInstance()->addCondition($owner->primaryKey, '!=', $owner->getId());

                if ($model->tryLoadBy($key, $owner->get($key))->isLoaded()) {
                    throw new Field\ValidationException([$key => ucwords($key) . ' with such value already exists'], $owner);
                }
            }
        });

        return $this;
    }

    /**
     * Returns array with useful debug info for var_dump.
     */
    public function __debugInfo(): array
    {
        $arr = [
            'key' => $this->getKey(),
            'value' => $this->get(),
        ];

        foreach ([
            'type', 'system', 'readOnly', 'ui', 'joinName',
        ] as $key) {
            if (isset($this->{$key})) {
                $arr[$key] = $this->{$key};
            }
        }

        return $arr;
    }
}
