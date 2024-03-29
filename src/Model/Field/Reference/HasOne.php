<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Reference;

use Phlex\Data\Model;

class HasOne extends Model\Field\Reference
{
    /**
     * Field type.
     *
     * Values are: 'string', 'text', 'boolean', 'integer', 'money', 'float',
     *             'date', 'datetime', 'time', 'array', 'object'.
     *
     * @var string
     */
    public $type = 'integer';

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
            $this->ourKey = $this->getKey() . '_id';
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
     * If our model is loaded, then return their model with respective record loaded.
     *
     * If our model is not loaded, then return their model with condition set.
     * This can happen in case of deep traversal $model->ref('Many')->ref('one_id'), for example.
     */
    public function getTheirEntity(array $defaults = []): Model
    {
        $theirModel = $this->createTheirModel($defaults);

        // add hook to set ourKey = null when record of referenced model is deleted
        $this->onHookToTheirModel($theirModel, Model::HOOK_AFTER_DELETE, function (Model $theirModel) {
            $this->getOurField()->setNull();
        });

        if ($this->getOurModel()->isLoaded()) {
            if ($ourValue = $this->getOurFieldValue()) {
                // if our model is loaded, then try to load referenced model
                $theirModel = $theirModel->tryLoadBy($this->getTheirKey($theirModel), $ourValue);
            } else {
                $theirModel->toEntity();
            }
        }

        // their model will be reloaded after saving our model to reflect changes in referenced fields
        $theirModel->reloadAfterSave = false;

        $this->onHookToTheirModel($theirModel, Model::HOOK_AFTER_SAVE, function (Model $theirModel) {
            $theirFieldValue = $this->getTheirFieldValue($theirModel);

            if ($this->getOurFieldValue() !== $theirFieldValue) {
                $this->getOurField()->set($theirFieldValue)->getOwner()->save();
            }

            $theirModel->reload();
        });

        return $theirModel;
    }

    public function set($value): self
    {
        // @todo GH check that class is same as theirModel class
        if ($value instanceof Model) {
            $value = $value->get($this->getTheirKey());
        }

        $this->getOwner()->getEntry()->set($this->getOurKey(), $value);

        return $this;
    }
}
