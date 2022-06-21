<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Field\Reference;

use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

class WithMany extends Model\Field\Reference\WithMany
{
    /**
     * Creates model that can be used for generating sub-queries.
     */
    public function refLink(array $defaults = []): Model
    {
        $theirModel = $this->createTheirModel($defaults);

        $this->getOurModel()->setOption(Persistence\Sql\Query::OPTION_FIELD_PREFIX);

        return $theirModel->addCondition(
            $this->getTheirKey($theirModel),
            $this->getOurField()
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
