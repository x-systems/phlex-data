<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Reference;

use Phlex\Data\Model;
use Phlex\Data\Persistence;

/**
 * ContainsMany reference.
 */
class ContainsMany extends ContainsOne
{
    protected function getDefaultPersistence(Model $theirModel)
    {
        $persistence = new Persistence\Array_([
            $this->table_alias => $this->getOurModel()->isLoaded() && $this->getOurFieldValue() !== null ? $this->getOurFieldValue() : [],
        ]);

        return $persistence->setCodecs($this->getPersistence()->getCodecs());
    }

    /**
     * Returns referenced model.
     */
    public function getTheirEntity(array $defaults = []): Model
    {
        // get model
        $theirModel = $this->createTheirModel(array_merge($defaults, [
            'table' => $this->table_alias,
        ]));

        // set some hooks for ref_model
        foreach ([Model::HOOK_AFTER_SAVE, Model::HOOK_AFTER_DELETE] as $spot) {
            $this->onHookToTheirModel($theirModel, $spot, function (Model $theirModel) {
                $this->getOurModel()->save([
                    $this->getOurKey() => $theirModel->export(null, null, false) ?: null,
                ]);
            });
        }

        return $theirModel;
    }
}
