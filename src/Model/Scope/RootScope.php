<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Scope;

use Phlex\Data\Exception;
use Phlex\Data\Model;

/**
 * The root scope object used in the Model::$scope property
 * All other conditions of the Model object are elements of the root scope
 * Scope elements are joined only using JUNCTION_AND.
 */
class RootScope extends Model\Scope
{
    /** @var Model */
    protected $model;

    protected function __construct(array $nestedConditions = [])
    {
        parent::__construct($nestedConditions, self::JUNCTION_AND);
    }

    public function setModel(Model $model)
    {
        if ($this->model !== $model) {
            $this->model = $model;

            $this->onChangeModel();
        }

        return $this;
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function negate()
    {
        throw new Exception('Model Scope cannot be negated!');
    }

    /**
     * @return Model\Scope
     */
    public static function createAnd(...$conditions)
    {
        return (parent::class)::createAnd(...$conditions);
    }

    /**
     * @return Model\Scope
     */
    public static function createOr(...$conditions)
    {
        return (parent::class)::createOr(...$conditions);
    }
}
