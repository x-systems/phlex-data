<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Reference;

use Phlex\Data\Model;

class HasMany extends Model\Field\Reference
{
    /**
     * Field type.
     *
     * Values are: 'string', 'text', 'boolean', 'integer', 'money', 'float',
     *             'date', 'datetime', 'time', 'array', 'object'.
     *
     * @var string
     */
    public $type = 'list';

    /**
     * Is it system field?
     * System fields will be always loaded and saved.
     *
     * @var bool
     */
    public $system = false;

    /**
     * Default value of field.
     *
     * @var mixed
     */
    public $default;

    /**
     * Setting this to true will never actually store
     * the field in the database. It will action as normal,
     * but will be skipped by update/insert.
     *
     * @var bool
     */
    public $never_persist = false;

    /**
     * Is field read only?
     * Field value may not be changed. It'll never be saved.
     * For example, expressions are read only.
     *
     * @var bool
     */
    public $readOnly = false;

    protected $table_alias = 'sub';

    /**
     * Defines a label to go along with this field. Use getCaption() which
     * will always return meaningful label (even if caption is null). Set
     * this property to any string.
     *
     * @var string
     */
    public $caption;

    /**
     * Array with UI flags like editable, visible and hidden.
     *
     * By default hasOne relation ID field should be editable in forms,
     * but not visible in grids. UI should respect these flags.
     *
     * @var array
     */
    public $ui = [];

    /**
     * Is field mandatory? By default fields are not mandatory.
     *
     * @var bool|string
     */
    public $mandatory = false;

    /**
     * Is field required? By default fields are not required.
     *
     * @var bool|string
     */
    public $required = false;

    /**
     * Should we use typecasting when saving/loading data to/from persistence.
     *
     * Value can be array [$typecast_save_callback, $typecast_load_callback].
     *
     * @var bool|array|null
     */
    public $typecast;

    /**
     * Should we use serialization when saving/loading data to/from persistence.
     *
     * Value can be array [$encode_callback, $decode_callback].
     *
     * @var bool|array|string|null
     */
    public $serialize;

    public $options = [];

    /**
     * Reference\HasOne will also add a field corresponding
     * to 'ourKey' unless it exists of course.
     */
    protected function doInitialize(): void
    {
        parent::doInitialize();

        if (!$this->ourKey) {
            $this->ourKey = $this->getKey() . '_list';
        }

        $ourModel = $this->getOurModel();

        if (!$ourModel->hasField($this->ourKey)) {
            $ourModel->addField($this->ourKey, [
                'type' => $this->type,
                'system' => $this->system,
                'joinName' => $this->joinName,
                'default' => $this->default,
                'never_persist' => $this->never_persist,
                'readOnly' => $this->readOnly,
                'caption' => $this->caption,
                'ui' => $this->ui,
                'mandatory' => $this->mandatory,
                'required' => $this->required,
                'serialize' => $this->serialize,
                'options' => $this->options,
            ]);
        }
    }

    /**
     * Returns referenced model with condition set.
     */
    public function getTheirEntity(array $defaults = []): Model
    {
        $theirModel = $this->createTheirModel($defaults);
        $ourModel = clone $this->getOurModel();

        if ($ourModel->isLoaded()) {
            return $theirModel->addCondition(
                $this->getTheirKey($theirModel),
                $this->getOurFieldValue()
            );
        }

        return $theirModel->addCondition(
            $ourModel->addCondition($this->getOurKey(), $this->getTheirField($theirModel))->toQuery()->exists()
        );
    }

    public function set($value): self
    {
        // @todo GH check that class is same as theirModel class
        if ($value instanceof Model) {
            $value = $value->export([$this->getTheirKey()]);
        }

        $this->getOwner()->getEntry()->set($this->getOurKey(), $value);

        return $this;
    }
}
