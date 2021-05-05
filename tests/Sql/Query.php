<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\SQL;

use Atk4\Dsql\Expression;
use Atk4\Dsql\Expressionable;
use Atk4\Dsql\Query as DsqlQuery;
use Doctrine\DBAL\Result;
use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

/**
 * Class to perform queries on SQL persistence.
 * Utilizes Atk4\Dsql\Query to perform the operations.
 *
 * @method DsqlQuery getDebugQuery()
 * @method DsqlQuery render()
 * @method DsqlQuery mode()
 * @method DsqlQuery reset()
 * @method DsqlQuery join()
 */
class Query extends Persistence\Query implements Expressionable
{
    /** @var DsqlQuery */
    protected $dsql;

    public function __construct(Model $model, Persistence\SQL $persistence = null)
    {
        parent::__construct($model, $persistence);

        $this->dsql = $model->persistence_data['dsql'] = $this->persistence->dsql();

        if ($model->table) {
            $this->dsql->table($model->table, $model->table_alias ?? null);
        }

        $this->addWithCursors();
    }

    protected function addWithCursors()
    {
        if (!$with = $this->model->with) {
            return;
        }

        foreach ($with as $alias => ['model' => $withModel, 'mapping' => $withMapping, 'recursive' => $recursive]) {
            // prepare field names
            $fieldsFrom = $fieldsTo = [];
            foreach ($withMapping as $from => $to) {
                $fieldsFrom[] = is_int($from) ? $to : $from;
                $fieldsTo[] = $to;
            }

            // prepare sub-query
            if ($fieldsFrom) {
                $withModel->onlyFields($fieldsFrom);
            }
            // 2nd parameter here strictly define which fields should be selected
            // as result system fields will not be added if they are not requested
            $subQuery = $withModel->toQuery()->select($fieldsFrom)->dsql();

            // add With cursor
            $this->dsql->with($subQuery, $alias, $fieldsTo ?: null, $recursive);
        }
    }

    protected function initSelect($fields = null): void
    {
        $this->dsql->reset('field');

        // do nothing on purpose
        if ($fields === false) {
            return;
        }

        // add fields
        if (is_array($fields)) {
            // Set of fields is strictly defined for purposes of export,
            // so we will ignore even system fields.
            foreach ($fields as $fieldName) {
                $this->addField($this->model->getField($fieldName));
            }
        } elseif ($this->model->only_fields) {
            $addedFields = [];

            // Add requested fields first
            foreach ($this->model->only_fields as $fieldName) {
                $field = $this->model->getField($fieldName);
                if (!$field->savesToPersistence()) {
                    continue;
                }
                $this->addField($field);
                $addedFields[$fieldName] = true;
            }

            // now add system fields, if they were not added
            foreach ($this->model->getFields() as $fieldName => $field) {
                if (!$field->loadsFromPersistence()) {
                    continue;
                }
                if ($field->system && !isset($addedFields[$fieldName])) {
                    $this->addField($field);
                }
            }
        } else {
            foreach ($this->model->getFields() as $fieldName => $field) {
                if (!$field->loadsFromPersistence()) {
                    continue;
                }
                $this->addField($field);
            }
        }
    }

    protected function addField(Field $field): void
    {
        $this->dsql->field($field, $field->useAlias() ? $field->short_name : null);
    }

    protected function initInsert(array $data): void
    {
        if ($data) {
            $this->dsql->mode('insert')->set($data);
        }
    }

    protected function initUpdate(array $data): void
    {
        if ($data) {
            $this->dsql->mode('update')->set($data);
        }
    }

    protected function initDelete(): void
    {
        $this->dsql->mode('delete');
    }

    protected function initExists(): void
    {
        $this->dsql = $this->dsql->exists();
    }

    protected function initCount($alias = null): void
    {
        $this->dsql->reset('field')->field('count(*)', $alias);
    }

    protected function initAggregate(string $functionName, $field, string $alias = null, bool $coalesce = false): void
    {
        $field = is_string($field) ? $this->model->getField($field) : $field;

        $expr = $coalesce ? "coalesce({$functionName}([]), 0)" : "{$functionName}([])";

        if (!$alias && $field instanceof Field\Expression) {
            $alias = $functionName . '_' . $field->short_name;
        }

        $this->dsql->reset('field')->field($this->dsql->expr($expr, [$field]), $alias);
    }

    protected function initField($fieldName, string $alias = null): void
    {
        if (!$fieldName) {
            throw new Exception('Field query requires field name');
        }

        $field = is_string($fieldName) ? $this->model->getField($fieldName) : $fieldName;

        if (!$alias && $field instanceof Field\Expression) {
            $alias = $field->short_name;
        }

        $this->dsql->reset('field')->field($field, $alias);
    }

    protected function initOrder(): void
    {
        $this->dsql->reset('order');

        foreach ((array) $this->order as [$field, $desc]) {
            if (is_string($field)) {
                $field = $this->model->getField($field);
            }

            if (!$field instanceof Expression && !$field instanceof Expressionable) {
                throw (new Exception('Unsupported order parameter'))
                    ->addMoreInfo('model', $this->model)
                    ->addMoreInfo('field', $field);
            }

            $this->dsql->order($field, $desc);
        }
    }

    protected function initLimit(): void
    {
        $this->dsql->reset('limit');

        if ($args = $this->getLimitArgs()) {
            $this->dsql->limit(...$args);
        }
    }

    protected function doExecute(): Result
    {
        return $this->dsql->execute();
    }

    public function doGetRows(): array
    {
        return $this->dsql->getRows();
    }

    protected function doGetRow(): ?array
    {
        return $this->dsql->getRow();
    }

    protected function doGetOne()
    {
        return $this->dsql->getOne();
    }

    public function getDsqlExpression(Expression $expression): Expression
    {
        return $this->dsql;
    }

    /**
     * Return the underlying Dsql object performing the query to DB.
     *
     * @return \Atk4\Dsql\Query
     */
    public function dsql()
    {
        return $this->dsql;
    }

    protected function initWhere(): void
    {
        $this->fillWhere($this->dsql, $this->scope);
    }

    protected static function fillWhere(DsqlQuery $query, Model\Scope\AbstractScope $condition)
    {
        if (!$condition->isEmpty()) {
            // peel off the single nested scopes to convert (((field = value))) to field = value
            $condition = $condition->simplify();

            // simple condition
            if ($condition instanceof Model\Scope\Condition) {
                $query->where(...$condition->toQueryArguments());
            }

            // nested conditions
            if ($condition instanceof Model\Scope) {
                $expression = $condition->isOr() ? $query->orExpr() : $query->andExpr();

                foreach ($condition->getNestedConditions() as $nestedCondition) {
                    self::fillWhere($expression, $nestedCondition);
                }

                $query->where($expression);
            }
        }
    }

    public function getDebug(): array
    {
        return array_merge([
            'sql' => $this->dsql->getDebugQuery(),
        ], parent::getDebug());
    }

    public function __call($method, $args)
    {
        return $this->dsql->{$method}(...$args);
    }
}
