<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Reference;

use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

class WithMany extends Model\Field\Reference
{
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
    protected function getOurFieldValue()
    {
        $ourModel = $this->getOurModel();

        if ($ourModel->isLoaded()) {
            return $ourModel->get($this->getOurKey());
        }

        // create expression based on existing conditions
        return $ourModel->toQuery()->field($this->getOurKey());
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

    /**
     * Creates model that can be used for generating sub-query actions.
     */
    public function refLink(array $defaults = []): Model
    {
        $theirModel = $this->createTheirModel($defaults);

        return $theirModel->addCondition(
            $this->getTheirKey($theirModel),
            $this->referenceOurValue()
        );
    }

    /**
     * Adds field as expression to our model.
     * Used in aggregate strategy.
     */
    public function addField(string $key, array $defaults = []): Model\Field
    {
        if (!isset($defaults['aggregate']) && !isset($defaults['concat']) && !isset($defaults['expr'])) {
            throw (new Exception('Aggregate field requires "aggregate", "concat" or "expr" specified to withMany()->addField()'))
                ->addMoreInfo('field', $key)
                ->addMoreInfo('defaults', $defaults);
        }

        $defaults['aggregate_relation'] = $this;

        $alias = $defaults['field'] ?? null;
        $field = $alias ?? $key;

        if (isset($defaults['concat'])) {
            $defaults['aggregate'] = new Persistence\Sql\Expression\GroupConcat($field, $defaults['concat']);
            $defaults['read_only'] = false;
            $defaults['never_save'] = true;
        }

        if (isset($defaults['expr'])) {
            $fx = function () use ($defaults, $alias) {
                $theirModelLinked = $this->refLink();

                return $theirModelLinked->toQuery()->field($theirModelLinked->expr(
                    $defaults['expr'],
                    $defaults['args'] ?? []
                ), $alias);
            };
            unset($defaults['args']);
        } elseif (is_object($defaults['aggregate'])) {
            $fx = fn () => $this->refLink()->toQuery()->field($defaults['aggregate'], $alias);
        } elseif ($defaults['aggregate'] === 'count' && !isset($defaults['field'])) {
            $fx = fn () => $this->refLink()->toQuery()->count($alias);
        } elseif (in_array($defaults['aggregate'], ['sum', 'avg', 'min', 'max', 'count'], true)) {
            $fx = fn () => $this->refLink()->toQuery()->aggregate($defaults['aggregate'], $field, null, true);
        } else {
            $fx = fn () => $this->refLink()->toQuery()->aggregate($defaults['aggregate'], $field);
        }

        return $this->getOurModel()->addExpression($key, array_merge([$fx], $defaults));
    }

    /**
     * Adds multiple fields.
     *
     * @see addField()
     *
     * @return $this
     */
    public function addFields(array $fields = [])
    {
        foreach ($fields as $defaults) {
            $key = $defaults[0];
            unset($defaults[0]);
            $this->addField($key, $defaults);
        }

        return $this;
    }
}
