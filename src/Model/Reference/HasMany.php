<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Reference;

use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

/**
 * Reference\HasMany class.
 */
class HasMany extends Model\Reference
{
    public function getTheirFieldName(): string
    {
        if ($this->theirFieldName) {
            return $this->theirFieldName;
        }

        // this is pure guess, verify if such field exist, otherwise throw
        // TODO probably remove completely in the future
        $ourModel = $this->getOurModel();
        $theirFieldName = $ourModel->table . '_' . $ourModel->primaryKey;
        if (!$this->createTheirModel()->hasField($theirFieldName)) {
            throw (new Exception('Their model does not contain fallback field'))
                ->addMoreInfo('their_fallback_field', $theirFieldName);
        }

        return $theirFieldName;
    }

    /**
     * Returns our field value or id.
     *
     * @return mixed
     */
    protected function getOurValue()
    {
        $ourModel = $this->getOurModel();

        if ($ourModel->isLoaded()) {
            return $this->ourFieldName
                ? $ourModel->get($this->ourFieldName)
                : $ourModel->getId();
        }

        // create expression based on existing conditions
        return $ourModel->toQuery()->field($this->getOurFieldName());
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
    public function ref(array $defaults = []): Model
    {
        return $this->createTheirModel($defaults)->addCondition(
            $this->getTheirFieldName(),
            $this->getOurValue()
        );
    }

    /**
     * Creates model that can be used for generating sub-query actions.
     */
    public function refLink(array $defaults = []): Model
    {
        $theirModelLinked = $this->createTheirModel($defaults)->addCondition(
            $this->getTheirFieldName(),
            $this->referenceOurValue()
        );

        return $theirModelLinked;
    }

    /**
     * Adds field as expression to our model.
     * Used in aggregate strategy.
     */
    public function addField(string $fieldName, array $defaults = []): Model\Field
    {
        if (!isset($defaults['aggregate']) && !isset($defaults['concat']) && !isset($defaults['expr'])) {
            throw (new Exception('Aggregate field requires "aggregate", "concat" or "expr" specified to hasMany()->addField()'))
                ->addMoreInfo('field', $fieldName)
                ->addMoreInfo('defaults', $defaults);
        }

        $defaults['aggregate_relation'] = $this;

        $alias = $defaults['field'] ?? null;
        $field = $alias ?? $fieldName;

        if (isset($defaults['concat'])) {
            $defaults['aggregate'] = Persistence\Sql\Expression::groupConcat($field, $defaults['concat']);
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
            $fx = function () use ($defaults, $alias) {
                return $this->refLink()->toQuery()->field($defaults['aggregate'], $alias);
            };
        } elseif ($defaults['aggregate'] === 'count' && !isset($defaults['field'])) {
            $fx = function () use ($alias) {
                return $this->refLink()->toQuery()->count($alias);
            };
        } elseif (in_array($defaults['aggregate'], ['sum', 'avg', 'min', 'max', 'count'], true)) {
            $fx = function () use ($defaults, $field) {
                return $this->refLink()->toQuery()->aggregate($defaults['aggregate'], $field, null, true);
            };
        } else {
            $fx = function () use ($defaults, $field) {
                return $this->refLink()->toQuery()->aggregate($defaults['aggregate'], $field);
            };
        }

        return $this->getOurModel()->addExpression($fieldName, array_merge([$fx], $defaults));
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
            $fieldName = $defaults[0];
            unset($defaults[0]);
            $this->addField($fieldName, $defaults);
        }

        return $this;
    }
}
