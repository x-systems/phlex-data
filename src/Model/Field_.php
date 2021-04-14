<?php

declare(strict_types=1);

namespace Phlex\Data\Model;

use Atk4\Dsql\Expression;
use Atk4\Dsql\Expressionable;
use Phlex\Core\DIContainerTrait;
use Phlex\Core\Factory;
use Phlex\Core\InitializerTrait;
use Phlex\Core\ReadableCaptionTrait;
use Phlex\Core\TrackableTrait;
use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;
use Phlex\Data\ValidationException;

class Field_ implements Expressionable
{
    use DIContainerTrait;
    use InitializerTrait {
        init as _init;
    }
    use ReadableCaptionTrait;
    use TrackableTrait;

    // {{{ Properties

    /**
     * Default value of field.
     *
     * @var mixed
     */
    public $default;

    /**
     * Field type.
     *
     * Values are:
     *      'string', 'text', 'boolean', 'integer', 'money', 'float',
     *      'date', 'datetime', 'time', 'array', 'object'.
     * Can also be set to unspecified type for your own custom handling.
     *
     * @var \Phlex\Data\Model\Field\Type
     */
    public $type = 'string';

    /**
     * If value of this field can be described by a model, this property
     * will contain reference to that model.
     */
    public $reference;

    /**
     * Actual field name.
     *
     * @var string|null
     */
    public $actual;

    /**
     * Join object.
     *
     * @var Join|null
     */
    public $join;

    /**
     * Is it system field?
     * System fields will be always loaded and saved.
     *
     * @var bool
     */
    public $system = false;

    /**
     * Setting this to true will never actually load or store
     * the field in the database. It will action as normal,
     * but will be skipped by load/iterate/update/insert.
     *
     * @var bool
     */
    public $never_persist = false;

    /**
     * Setting this to true will never actually store
     * the field in the database. It will action as normal,
     * but will be skipped by update/insert.
     *
     * @var bool
     */
    public $never_save = false;

    /**
     * Is field read only?
     * Field value may not be changed. It'll never be saved.
     * For example, expressions are read only.
     *
     * @var bool
     */
    public $read_only = false;

    /**
     * Defines a label to go along with this field. Use getCaption() which
     * will always return meaningful label (even if caption is null). Set
     * this property to any string.
     *
     * @var string
     */
    public $caption;

    /**
     * Array with UI flags like editable, visible and hidden and settings
     * like caption.
     *
     * @var array
     */
    public $ui = [];

    /**
     * Array with Persistence settings like format, timezone etc.
     * It's job of Persistence to take these settings into account if needed.
     *
     * @var array
     */
    public $persistence = [];

    /**
     * Mandatory field must not be null. The value must be set, even if
     * it's an empty value.
     *
     * Think about this property as "NOT NULL" property.
     *
     * Can contain error message for UI.
     *
     * @var bool|string
     */
    public $mandatory = false;

    /**
     * Required field must have non-empty value. A null value is considered empty too.
     *
     * Think about this property as !empty($value) property with some exceptions.
     *
     * This property takes precedence over $mandatory property.
     *
     * Can contain error message for UI.
     *
     * @var bool|string
     */
    public $required = false;

    /**
     * Should we use typecasting when saving/loading data to/from persistence.
     *
     * Value can be array ['save' => $typecast_save_callback, 'load' => $typecast_load_callback].
     *
     * @var bool|array|null
     */
    public $typecast;

    /**
     * Should we use serialization when saving/loading data to/from persistence.
     *
     * Value can be array ['encode' => $encode_callback, 'decode' => $decode_callback].
     *
     * @var bool|array|null
     */
    public $serialize;

    // }}}

    // {{{ Core functionality

    /**
     * Constructor. You can pass field properties as array.
     */
    public function __construct(array $defaults = [])
    {
        foreach ($defaults as $key => $val) {
            if (is_array($val)) {
                $this->{$key} = array_merge(isset($this->{$key}) && is_array($this->{$key}) ? $this->{$key} : [], $val);
            } else {
                $this->{$key} = $val;
            }
        }
    }

    /**
     * Initialization.
     */
    protected function init()
    {
        $this->_init();

        $this->type = Factory::factory(Field\Type::resolve($this->type));
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
                throw new ValidationException([$this->name => 'Must not be null or empty']);
            }
        }

        try {
            return $this->type->normalize($value);
        } catch (Field\Type\ValidationException $e) {
            throw new ValidationException([$this->name => $e->getMessage()]);
        }
    }

    public static function isExpression($value)
    {
        return $value instanceof Expression || $value instanceof Expressionable;
    }

    /**
     * Casts field value to string.
     *
     * @param mixed $value Optional value
     *
     * @return string|mixed
     */
    public function toString()
    {
        return $this->type->toString($this->get());
    }

    /**
     * Returns field value.
     *
     * @return mixed
     */
    public function get()
    {
        return $this->owner[$this->short_name];
    }

    /**
     * Sets field value.
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function set($value)
    {
        $this->owner->set($this->short_name, $value);

        return $this;
    }

    /**
     * This method can be extended. See Model::compare for
     * use examples.
     *
     * @param mixed $value
     */
    public function compare($value): bool
    {
        return $this->get() === $value;
    }

    public function getPersistenceName(): string
    {
        return $this->actual ?? $this->short_name;
    }

    /**
     * Should this field use alias?
     *
     * @return bool
     */
    public function useAlias()
    {
        return isset($this->actual);
    }

    // }}}

    // {{{ Scope condition

    /**
     * Returns arguments to be used for query on this field based on the condition.
     *
     * @param string|null $operator one of Scope\Condition operators
     * @param mixed       $value    the condition value to be handled
     */
    public function getQueryArguments($operator, $value): array
    {
        $skipValueTypecast = [
            Scope\Condition::OPERATOR_LIKE,
            Scope\Condition::OPERATOR_NOT_LIKE,
            Scope\Condition::OPERATOR_REGEXP,
            Scope\Condition::OPERATOR_NOT_REGEXP,
        ];

        if (!in_array($operator, $skipValueTypecast, true)) {
            if (is_array($value)) {
                $value = array_map(function ($option) {
                    return $this->getOwner()->persistence->typecastSaveField($this, $option);
                }, $value);
            } else {
                $value = $this->getOwner()->persistence->typecastSaveField($this, $value);
            }
        }

        return [$this, $operator, $value];
    }

    // }}}

    // {{{ Handy methods used by UI and in other places

    /**
     * Returns if field should be editable in UI.
     */
    public function isEditable(): bool
    {
        return $this->ui['editable'] ?? (($this->read_only || $this->never_persist) ? false : !$this->system);
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
     * Returns true if field allows NULL values.
     */
    public function canBeNull(): bool
    {
        return $this->mandatory === false;
    }

    /**
     * Returns true if field allows EMPTY values like empty string and NULL.
     */
    public function canBeEmpty(): bool
    {
        return $this->mandatory === false && $this->required === false;
    }

    /**
     * Returns field caption for use in UI.
     */
    public function getCaption(): string
    {
        return $this->caption ?? $this->ui['caption'] ?? $this->readableCaption($this->short_name);
    }

    /**
     * Returns typecasting callback if defined.
     *
     * Typecasting can be defined as (in order of precedence)
     *
     * * affects all typecasting for the field
     * $user->addField('dob', ['Date', 'typecast'=>[$encode_fx, $decode_fx]]);
     *
     * * affects typecasting for specific persistence class
     * $user->addField('dob', ['Date', 'persistence'=>['Phlex\Data\Persistence\SQL'=>['typecast'=>[$encode_fx, $decode_fx]]]]);
     *
     * * affects typecasting for all persistences
     * $user->addField('dob', ['Date', 'persistence'=>['typecast'=>[$encode_fx, $decode_fx]]]);
     *
     * * default typecasting (if none of above set) will be used for all fields of the class defined in field methods
     * typecastSave / typecastLoad based on the $mode
     *
     * @param string $mode - load|save
     *
     * @return callable|false
     */
    public function getTypecaster($mode)
    {
        // map for backward compatibility with definition
        // [typecast_save_callback, typecast_load_callback]
        $map = [
            'save' => 0,
            'load' => 1,
        ];

        $typecast = $this->getPersistenceSetting('typecast');

        // default typecaster is method in the field named typecastSave or typecastLoad if such method exists
        $default = method_exists($this, 'typecast' . ucfirst($mode)) ? [$this, 'typecast' . ucfirst($mode)] : false;

        $fx = $typecast[$mode] ?? $typecast[$map[$mode]] ?? $default;

        return is_callable($fx) ? $fx : false;
    }

    /**
     * Returns serialize callback if defined.
     *
     * @param string $mode - encode|decode
     *
     * @return callable|false
     */
    public function getSerializer($mode)
    {
        // map for backward compatibility with definition
        // [encode_callback, decode_callback]
        $map = [
            'encode' => 0,
            'decode' => 1,
        ];

        $fx = $this->serialize[$mode] ?? $this->serialize[$map[$mode]] ?? false;

        return is_callable($fx) ? $fx : false;
    }

    /**
     * Returns persistence setting defined
     * Order of precedence is: field specific, persistence specific, persistence general.
     *
     * Below examples consider $key = 'typecast'
     * Field specific setting is defined in a field property with $key as name
     * e.g. $field->typecast = [$encode_fx, $decode_fx]
     *
     * Persistence specific setting is defined in $field->persistence array
     * e.g. $field->persistence = [\Phlex\Data\Persistence\SQL::class => ['typecast' => [$encode_fx, $decode_fx]]] or
     * e.g. $field->persistence = ['SQL' => ['typecast' => [$encode_fx, $decode_fx]]]
     * The latter checks only the persistence class name ignoring the namespace.
     * Both syntaxes are valid but first one has precedence
     *
     * Persistence general setting is defined in $field->persistence array
     * e.g. $field->persistence = ['typecast' => [$encode_fx, $decode_fx]]
     *
     * @param string $key
     *
     * @return array
     */
    public function getPersistenceSetting($key)
    {
        // persistence specific typecast
        $specific = null;
        if ($persistence = $this->hasPersistence()) {
            $classFull = get_class($persistence);
            $classBare = implode('', array_slice(explode('\\', $classFull), -1));

            foreach ([$classFull, $classBare] as $class) {
                $specific = $this->persistence[$class][$key] ?? $specific;
            }
        }

        // get the setting definition to be applied
        // field specific or persistence specific or persistence general or none
        return $this->{$key} ?? $specific ?? $this->persistence[$key] ?? [];
    }

    public function hasPersistence()
    {
        return $this->owner ? $this->owner->persistence : false;
    }

    // }}}

    /**
     * When field is used as expression, this method will be called.
     * Universal way to convert ourselves to expression. Off-load implementation into persistence.
     */
    public function getDSQLExpression(Expression $expression): Expression
    {
        if (!$this->getOwner()->persistence || !$this->getOwner()->persistence instanceof Persistence\Sql) {
            throw new Exception([
                'Field must have SQL persistence if it is used as part of expression',
                'persistence' => $this->owner->persistence ?? null,
            ]);
        }

        return $this->getOwner()->persistence->getFieldSQLExpression($this, $expression);
    }

    // {{{ Debug Methods

    /**
     * Returns array with useful debug info for var_dump.
     */
    public function __debugInfo(): array
    {
        $arr = [
            'short_name' => $this->short_name,
            'value' => $this->get(),
        ];

        foreach ([
            'type', 'system', 'never_persist', 'never_save', 'read_only', 'ui', 'persistence', 'join',
        ] as $key) {
            if (isset($this->{$key})) {
                $arr[$key] = $this->{$key};
            }
        }

        return $arr;
    }

    // }}}
}
