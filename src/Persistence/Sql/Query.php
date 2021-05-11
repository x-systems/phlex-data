<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql;

use Doctrine\DBAL;
use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

/**
 * Class to perform queries on Sql persistence.
 *
 * @method Persistence\Sql getPersistence()
 * @method Statement getDebugQuery()
 * @method Statement render()
 * @method Statement mode()
 * @method Statement reset()
 * @method Statement join()
 */
class Query extends Persistence\Query implements Expressionable
{
    public const MODE_REPLACE = 'replace';
    public const MODE_TRUNCATE = 'truncate';

    /** @var Statement */
    protected $statement;

    public function __construct(Model $model)
    {
        parent::__construct($model);

        $this->statement = $this->getPersistence()->statement();

        if ($model->table) {
            $this->statement->table($model->table, $model->table_alias ?? null);
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
            $subQuery = $withModel->toQuery()->select($fieldsFrom)->getStatement();

            // add With cursor
            $this->statement->with($subQuery, $alias, $fieldsTo ?: null, $recursive);
        }
    }

    protected function initSelect($fields = null): void
    {
        $this->statement->reset('field');

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

    protected function addField(Model\Field $field): void
    {
        $this->statement->field($field, $field->useAlias() ? $field->short_name : null);
    }

    protected function initInsert(array $data): void
    {
        if ($data) {
            $this->statement->mode('insert')->set($data);
        }
    }

    protected function initUpdate(array $data): void
    {
        if ($data) {
            $this->statement->mode('update')->set($data);
        }
    }

    protected function initDelete(): void
    {
        $this->statement->mode('delete');
    }

    protected function initExists(): void
    {
        $this->statement = $this->getPersistence()->statement()->select()->option('exists')->field($this->statement);
    }

    protected function initCount($alias = null): void
    {
        $this->statement->reset('field')->field('count(*)', $alias);
    }

    protected function initAggregate(string $functionName, $field, string $alias = null, bool $coalesce = false): void
    {
        $field = is_string($field) ? $this->model->getField($field) : $field;

        $expr = $coalesce ? "coalesce({$functionName}([]), 0)" : "{$functionName}([])";

        if (!$alias && $field instanceof Field\Expression) {
            $alias = $functionName . '_' . $field->short_name;
        }

        $this->statement->reset('field')->field($this->statement->expr($expr, [$field]), $alias);
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

        $this->statement->reset('field')->field($field, $alias);
    }

    protected function initOrder(): void
    {
        $this->statement->reset('order');

        foreach ((array) $this->order as [$field, $desc]) {
            if (is_string($field)) {
                $field = $this->model->getField($field);
            }

            if (!$field instanceof Expressionable) {
                throw (new Exception('Unsupported order parameter'))
                    ->addMoreInfo('model', $this->model)
                    ->addMoreInfo('field', $field);
            }

            $this->statement->order($field, $desc);
        }
    }

    protected function initLimit(): void
    {
        $this->statement->reset('limit');

        if ($args = $this->getLimitArgs()) {
            $this->statement->limit(...$args);
        }
    }

    protected function doExecute(): DBAL\Result
    {
        return $this->getPersistence()->execute($this);
    }

    public function doGetRows(): array
    {
        return $this->execute()->fetchAllAssociative();
    }

    protected function doGetRow(): ?array
    {
        return $this->execute()->fetchAssociative() ?: null;
    }

    protected function doGetOne()
    {
        return $this->execute()->fetchOne();
    }

    public function toExpression(): Expression
    {
        return $this->statement->toExpression();
    }

    public function getStatement(): Statement
    {
        return $this->statement;
    }

    protected function initWhere(): void
    {
        $this->fillWhere($this->statement, $this->scope);
    }

    protected static function fillWhere(Statement $statement, Model\Scope\AbstractScope $condition)
    {
        if (!$condition->isEmpty()) {
            // peel off the single nested scopes to convert (((field = value))) to field = value
            $condition = $condition->simplify();

            // simple condition
            if ($condition instanceof Model\Scope\Condition) {
                $statement->where(...$condition->toQueryArguments());
            }

            // nested conditions
            if ($condition instanceof Model\Scope) {
                $expression = $condition->isOr() ? $statement->orExpr() : $statement->andExpr();

                foreach ($condition->getNestedConditions() as $nestedCondition) {
                    self::fillWhere($expression, $nestedCondition);
                }

                $statement->where($expression);
            }
        }
    }

    public function render(): string
    {
        return $this->withMode()->getStatement()->render();
    }

    public function getDebug(): array
    {
        return array_merge([
            'sql' => $this->statement->getDebugQuery(),
        ], parent::getDebug());
    }

    public function __call($method, $args)
    {
        return $this->statement->{$method}(...$args);
    }
}
