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
 * @method Statement       getDebugQuery()
 * @method Statement       mode()
 * @method Statement       reset()
 * @method Statement       join()
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

        $this->addTable();

        $this->addWithCursors();
    }

    protected function addTable()
    {
        if (!$table = $this->model->table) {
            return;
        }

        if (is_array($table)) {
            $tables = [];
            foreach ($table as $model) {
                if ($model instanceof Model) {
                    $model = $model->toQuery()->select();
                }

                $tables[] = $model;
            }

            $table = $tables;
        }

        $this->statement->table($table, $this->model->table_alias ?? null);
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
            foreach ($fields as $key) {
                $this->addField($this->model->getField($key));
            }
        } elseif ($this->model->only_fields) {
            $addedFields = [];

            // Add requested fields first
            foreach ($this->model->only_fields as $key) {
                $field = $this->model->getField($key);
                if (!$field->loadsFromPersistence()) {
                    continue;
                }
                $this->addField($field);
                $addedFields[$key] = true;
            }

            if (!$this->model->getOption(Persistence\Query::OPTION_MODEL_STRICT_ONLY_FIELDS)) {
                // now add system fields, if they were not added
                foreach ($this->model->getFields() as $key => $field) {
                    if (!$field->loadsFromPersistence()) {
                        continue;
                    }
                    if ($field->system && !isset($addedFields[$key])) {
                        $this->addField($field);
                    }
                }
            }
        } else {
            foreach ($this->model->getFields() as $key => $field) {
                if (!$field->loadsFromPersistence()) {
                    continue;
                }
                $this->addField($field);
            }
        }
    }

    protected function addField(Model\Field $field): void
    {
        $this->statement->field($field, $field->getAlias()); // @phpstan-ignore-line $field here is of Sql\Field type
    }

    protected function initInsert(array $data): void
    {
        $this->statement->mode('insert')->set($data);
    }

    protected function initUpdate(array $data): void
    {
        $this->statement->mode('update')->set($data);
    }

    protected function initDelete(): void
    {
        $this->statement->mode('delete');
    }

    protected function initExists(): void
    {
        $this->statement = $this->statement->exists();
    }

    protected function initCount($alias = null): void
    {
        $this->statement->reset('field')->field(new Expression('count(*)'), $alias);
    }

    protected function initAggregate(string $functionName, $field, string $alias = null, bool $coalesce = false): void
    {
        $field = is_string($field) ? $this->model->getField($field) : $field;

        $expr = $coalesce ? "coalesce({$functionName}([]), 0)" : "{$functionName}([])";

        if (!$alias && $field instanceof Field\Expression) {
            $alias = $functionName . '_' . $field->elementId;
        }

        $this->statement->reset('field')->field(new Expression($expr, [$field]), $alias);
    }

    protected function initField($key, string $alias = null): void
    {
        if (!$key) {
            throw new Exception('Field query requires field name');
        }

        $field = is_string($key) ? $this->model->getField($key) : $key;

        if (!$alias && $field instanceof Field\Expression) {
            $alias = $field->elementId;
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

    public function toSqlExpression(): Expression
    {
        return $this->withMode()->getStatement()->toSqlExpression();
    }

    public function getStatement(): Statement
    {
        return $this->statement;
    }

    protected function initWhere(): void
    {
        $this->fillWhere($this->statement, $this->scope);
    }

    protected static function fillWhere(Expression $statement, Model\Scope\AbstractScope $condition)
    {
        if (!$condition->isEmpty()) {
            // peel off the single nested scopes to convert (((field = value))) to field = value
            $condition = $condition->simplify();

            // simple condition
            if ($condition instanceof Model\Scope\Condition) {
                if ($args = $condition->toQueryArguments()) {
                    $statement->where(...$args); // @phpstan-ignore-line
                }
            } elseif ($condition instanceof Model\Scope) { // nested conditions
                $expression = $condition->isOr() ? $statement->or() : $statement->and();

                foreach ($condition->getNestedConditions() as $nestedCondition) {
                    self::fillWhere($expression, $nestedCondition);
                }

                $statement->where($expression); // @phpstan-ignore-line
            }
        }
    }

    public function render(): string
    {
        return $this->withMode()->getStatement()->render();
    }

    public function renderDebug(): string
    {
        return $this->withMode()->getStatement()->getDebugQueryFormatted();
    }

    public function getDebug(): array
    {
        return array_merge([
            'sql' => $this->statement->getDebugQueryFormatted(),
        ], parent::getDebug());
    }

    public function __call($method, $args)
    {
        return $this->statement->{$method}(...$args);
    }
}
