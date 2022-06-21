<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Reference;

use Phlex\Data\Exception;
use Phlex\Data\Model;

class WithMany extends Model\Field\Reference
{
    /**
     * Returns referenced model with condition set.
     */
    public function getTheirEntity(array $defaults = []): Model
    {
        $theirModel = $this->createTheirModel($defaults);

        return $theirModel->addCondition(
            $this->getTheirKey($theirModel),
            $this->getOurFieldValue()
        );
    }

    public function getTheirKey(Model $theirModel = null): string
    {
        if ($this->theirKey) {
            return $this->theirKey;
        }

        // this is pure guess, verify if such field exist, otherwise throw
        // TODO probably remove completely in the future
        $ourModel = $this->getOurModel();
        $theirKey = $ourModel->table . '_' . $ourModel->primaryKey;
        $theirModel ??= $this->createTheirModel();

        if (!$theirModel->hasField($theirKey)) {
            throw (new Exception('Their model does not contain fallback primary key'))
                ->addMoreInfo('theirKey', $theirKey);
        }

        return $theirKey;
    }

    /**
     * Returns our field value or id.
     *
     * @return mixed
     */
    public function getOurFieldValue()
    {
        $ourModel = $this->getOurModel();

        if ($ourModel->isLoaded()) {
            return $ourModel->get($this->getOurKey());
        }

        // create expression based on existing conditions
        return $ourModel->toQuery()->field($this->getOurKey());
    }
}
