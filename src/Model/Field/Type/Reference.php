<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

use Phlex\Data\Field;
use Phlex\Data\Model;

/**
 * Reference implements a link between one model and another. The basic components for
 * a reference is ability to generate the destination model, which is returned through
 * getModel() and that's pretty much it.
 *
 * It's possible to extend the basic reference with more meaningful references.
 *
 * @property Model $owner definition of our model
 */
class Reference
{
    use \Phlex\Core\DiContainerTrait;
    use \Phlex\Core\InitializerTrait {
        init as _init;
    }
    use \Phlex\Core\TrackableTrait;

    /**
     * Definition of the destination their model, that can be either an object, a
     * callback or a string. This can be defined during initialization and
     * then used inside getModel() to fully populate and associate with
     * persistence.
     *
     * @var Model|string|array
     */
    public $model;

    /**
     * This is an optional property which can be used by your implementation
     * to store field-level relationship based on a common field matching.
     *
     * @var string
     */
    protected $ourField;

    /**
     * This is an optional property which can be used by your implementation
     * to store field-level relationship based on a common field matching.
     *
     * @var string
     */
    protected $theirField;

    public function getOurModel(): Model
    {
        return $this->owner;
    }

    /**
     * Returns destination model that is linked through this reference. Will apply
     * necessary conditions.
     *
     * @param array $defaults Properties
     */
    public function getTheirModel($defaults = []): Model
    {
        // set table_alias
        $defaults['table_alias'] = $defaults['table_alias'] ?? $this->table_alias;

        if (is_object($this->model)) {
            if ($this->model instanceof \Closure) {
                // if model is Closure, then call the closure and whci should return a model
                $theirModel = ($this->model)($this->getOurModel(), $this, $defaults);
            } else {
                // if model is set, then use clone of this model
                $theirModel = clone $this->model;
            }

            return $this->addToPersistence($theirModel, $defaults);
        }

        // add model from seed
        if (is_array($this->model)) {
            $modelDefaults = $this->model;
            $theirModelSeed = [$modelDefaults[0]];

            unset($modelDefaults[0]);

            $defaults = array_merge($modelDefaults, $defaults);
        } else {
            $theirModelSeed = [$this->model];
        }

        $theirModel = $this->factory($theirModelSeed, $defaults);

        return $this->addToPersistence($theirModel, $defaults);
    }

    protected function getOurField(): Field
    {
        return $this->getOurModel()->getField($this->getOurFieldName());
    }

    protected function getOurFieldName(): string
    {
        return $this->ourField ?: $this->getOurModel()->id_field;
    }

    protected function getOurFieldValue()
    {
        return $this->getOurField()->get();
    }

    /**
     * Adds model to persistence.
     *
     * @param Model $theirModel
     * @param array $defaults
     */
    protected function addToPersistence($theirModel, $defaults = []): Model
    {
        if (!$theirModel->persistence && $persistence = $this->getDefaultPersistence($theirModel)) {
            $persistence->add($theirModel, $defaults);
        }

        return $theirModel;
    }

    /**
     * Returns default persistence for theirModel.
     *
     * @return Persistence|false
     */
    protected function getDefaultPersistence(Model $theirModel)
    {
        $ourModel = $this->getOurModel();

        // this will be useful for containsOne/Many implementation in case when you have
        // SQL_Model->containsOne()->hasOne() structure to get back to SQL persistence
        // from Array persistence used in containsOne model
        if ($ourModel->contained_in_root_model && $ourModel->contained_in_root_model->persistence) {
            return $ourModel->contained_in_root_model->persistence;
        }

        return $ourModel->persistence ?: false;
    }

    /**
     * Returns referenced model without any extra conditions. However other
     * relationship types may override this to imply conditions.
     *
     * @param array $defaults Properties
     */
    public function ref($defaults = []): Model
    {
        return $this->getTheirModel($defaults);
    }

    /**
     * Returns referenced model without any extra conditions. Ever when extended
     * must always respond with Model that does not look into current record
     * or scope.
     *
     * @param array $defaults Properties
     */
    public function refModel($defaults = []): Model
    {
        return $this->getTheirModel($defaults);
    }

    // {{{ Debug Methods

    /**
     * List of properties to show in var_dump.
     */
    protected $__debug_fields = ['model', 'ourField', 'theirField'];

    /**
     * Returns array with useful debug info for var_dump.
     */
    public function __debugInfo(): array
    {
        $arr = [];
        foreach ($this->__debug_fields as $k => $v) {
            $k = is_numeric($k) ? $v : $k;
            if (isset($this->{$v})) {
                $arr[$k] = $this->{$v};
            }
        }

        return $arr;
    }

    // }}}
}
