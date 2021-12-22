<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Reference;

use Phlex\Data\Model;
use Phlex\Data\Persistence;

class HasOnePolymorphic extends HasOne
{
    protected $ourTypeKey;

    protected function doInitialize(): void
    {
        parent::doInitialize();

        if (!$this->ourKey) {
            $this->ourKey = $this->link;
        }

        $ourModel = $this->getOurModel();

        $this->ourTypeKey ??= $this->ourKey . '_type';

        if (!$ourModel->hasField($this->ourTypeKey)) {
            $ourModel->addField($this->ourTypeKey, [
                'type' => 'string',
                'system' => true,
            ]);
        }
    }

    /**
     * Returns our field or id field.
     */
    protected function referenceOurValue(): Model\Field
    {
        $this->getOurModel()->setOption(Persistence\Sql::OPTION_USE_TABLE_PREFIX);

        return $this->getOurField();
    }

    /**
     * If our model is loaded, then return their model with respective record loaded.
     *
     * If our model is not loaded, then return their model with condition set.
     * This can happen in case of deep traversal $model->ref('Many')->ref('one_id'), for example.
     */
    public function ref(array $defaults = []): Model
    {
        $theirModel = $this->createTheirModel($defaults);

        // add hook to set ourKey = null when record of referenced model is deleted
        $this->onHookToTheirModel($theirModel, Model::HOOK_AFTER_DELETE, function (Model $theirModel) {
            $this->getOurField()->setNull();
        });

        if ($this->getOurModel()->isEntity()) {
            if ($ourValue = $this->getOurFieldValue()) {
                // if our model is loaded, then try to load referenced model
                $theirModel = $theirModel->tryLoadBy($this->getTheirKey($theirModel), $ourValue);
            } else {
                $theirModel = $theirModel->createEntity();
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
}
