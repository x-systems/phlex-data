<?php

declare(strict_types=1);

namespace Phlex\Data\Model;

use Phlex\Core\DiContainerTrait;
use Phlex\Core\InitializerTrait;
use Phlex\Core\TrackableTrait;
use Phlex\Data\Exception;
use Phlex\Data\Model;

/**
 * @method Model getOwner()
 */
class SoftDeleteController
{
    use DiContainerTrait;
    use InitializerTrait;
    use TrackableTrait;

    public const TRACKABLE_ID = self::class . '@softDeleteController';

    public const OPTION_DISABLED = self::class . '@disabled';
    public const OPTION_RETRIEVE = self::class . '@retrieve';

    public const HOOK_BEFORE_DEACTIVATE = self::class . '@beforeDeactivate';
    public const HOOK_AFTER_DEACTIVATE = self::class . '@afterDeactivate';
    public const HOOK_BEFORE_REACTIVATE = self::class . '@beforeReactivate';
    public const HOOK_AFTER_REACTIVATE = self::class . '@afterReactivate';

    public const RETRIEVE_ALL = null;
    public const RETRIEVE_ACTIVE = true;
    public const RETRIEVE_INACTIVE = false;

    protected $useFieldName = 'sdflag_active';

    protected $defaultRetrieveMode = self::RETRIEVE_ACTIVE;

    public function __construct($defaults = [])
    {
        $this->elementId = self::TRACKABLE_ID;

        $this->setDefaults($defaults);
    }

    protected function doInitialize(): void
    {
        $model = $this->getOwner();
        if ($model->getOption(self::OPTION_DISABLED)) {
            return;
        }

        $model->addField($this->useFieldName, ['type' => 'boolean', 'caption' => 'Soft Delete Status', 'system' => true, 'default' => true]);

        $caption = function ($condition) {
            $active = $condition->getModel()->getOption(self::OPTION_RETRIEVE, $this->defaultRetrieveMode);

            if ($active === null) {
                return 'Any value';
            }

            return $active ? 'Active' : 'Deactivated';
        };

        $value = function (Model\Scope\Condition $condition) {
            $active = $condition->getModel()->getOption(self::OPTION_RETRIEVE, $this->defaultRetrieveMode);

            if ($active === null) {
                $condition->clear();
            }

            return $active;
        };

        $model->addCondition($this->useFieldName, new Model\Scope\Placeholder($caption, $value));

        $model->addMethod('deactivate', \Closure::fromCallable([self::class, 'deactivate']));
        $model->addMethod('reactivate', \Closure::fromCallable([self::class, 'reactivate']));
        $model->addMethod('isActive', \Closure::fromCallable([self::class, 'isActive']));
        $model->addMethod('ignoringSoftDeleteFlag', \Closure::fromCallable([self::class, 'ignoringSoftDeleteFlag']));

        $model->onHook(Model::HOOK_SET_OPTION, \Closure::fromCallable([self::class, 'onModelOptionChange']));
    }

    public static function onModelOptionChange(Model $model, string $option = null)
    {
        if ($option === self::OPTION_DISABLED) {
            throw new Exception('Option ' . self::OPTION_DISABLED . ' cannot be changed after initialization');
        }
    }

    public static function deactivate(Model $model)
    {
        if (!$model->isLoaded()) {
            throw (new Exception('Model must be loaded before deactivating'))
                ->addMoreInfo('model', $model);
        }

        $id = $model->getId();
        if ($model->hook(self::HOOK_BEFORE_DEACTIVATE) === false) {
            return $model;
        }

        $model->save([$model->getElement(self::TRACKABLE_ID)->useFieldName => false]);

        $model->hook(self::HOOK_AFTER_DEACTIVATE, [$id]);

        return $model;
    }

    public static function reactivate(Model $model)
    {
        if (!$model->isLoaded()) {
            throw (new Exception('Model must be loaded before reactivating'))
                ->addMoreInfo('model', $model);
        }

        if ($model->hook(self::HOOK_BEFORE_REACTIVATE) === false) {
            return $model;
        }

        $model->save([$model->getElement(self::TRACKABLE_ID)->useFieldName => true]);

        $model->hook(self::HOOK_AFTER_REACTIVATE, [$model->getId()]);

        return $model;
    }

    public static function isActive(Model $model): bool
    {
        $model->assertIsEntity();

        return (bool) $model->get($model->getElement(self::TRACKABLE_ID)->useFieldName);
    }

    public static function ignoringSoftDeleteFlag(Model $model, \Closure $fx)
    {
        $model = clone $model;

        $model->setOption(self::OPTION_RETRIEVE, self::RETRIEVE_ALL);

        return $fx($model);
    }
}
