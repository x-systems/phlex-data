<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql;

use Phlex\Data\Model;
use Phlex\Data\Persistence;

/**
 * Provides model joining functionality specific for the Sql persistence.
 *
 * @property Persistence\Sql $persistence
 */
class Join extends Model\Join implements Expressionable
{
    use Model\ElementTrait;

    /**
     * By default we create ON expression ourselves, but if you want to specify
     * it, use the 'on' property.
     *
     * @var Expression|string|null
     */
    protected $on;

    /**
     * Will use either foreign_alias or create #join_<table>.
     */
    public function getDesiredName(): string
    {
        return '_' . ($this->foreign_alias ?: $this->foreign_table[0]);
    }

    public function toSqlExpression(): Expression
    {
        /*
        // If our Model has expr() method (inherited from Persistence\Sql) then use it
        if ($this->getOwner()->hasMethod('expr')) {
            return $this->getOwner()->expr('{}.{}', [$this->foreign_alias, $this->foreign_field]);
        }

        // Otherwise call it from expression itself
        return $expr->expr('{}.{}', [$this->foreign_alias, $this->foreign_field]);
        */

        // Romans: Join\Sql shouldn't even be called if expr is undefined. I think we should leave it here to produce error.
        return new Expression('{}.{}', [$this->foreign_alias, $this->foreign_field]);
    }

    /**
     * This method is to figure out stuff.
     */
    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->getOwner()->setOption(Persistence\Sql::OPTION_USE_TABLE_PREFIX);

        // If kind is not specified, figure out join type
        if (!isset($this->kind)) {
            $this->kind = $this->weak ? 'left' : 'inner';
        }

        // Our short name will be unique
        if (!$this->foreign_alias) {
            $this->foreign_alias = ($this->getOwner()->table_alias ?: '') . $this->elementId;
        }

        $this->onHookShortToOwner(Persistence\Query::HOOK_INIT_SELECT, \Closure::fromCallable([$this, 'initSelectQuery']));

        // Add necessary hooks
        if ($this->reverse) {
            $this->onHookShortToOwner(Model::HOOK_AFTER_INSERT, \Closure::fromCallable([$this, 'afterInsert']));
            $this->onHookShortToOwner(Model::HOOK_BEFORE_UPDATE, \Closure::fromCallable([$this, 'beforeUpdate']));
            $this->onHookShortToOwner(Model::HOOK_BEFORE_DELETE, \Closure::fromCallable([$this, 'doDelete']), [], -5);
            $this->onHookShortToOwner(Model::HOOK_AFTER_LOAD, \Closure::fromCallable([$this, 'afterLoad']));
        } else {
            // Master field indicates ID of the joined item. In the past it had to be
            // defined as a physical field in the main table. Now it is a model field
            // so you can use expressions or fields inside joined entities.
            // If string specified here does not point to an existing model field
            // a new basic field is inserted and marked hidden.
            if (is_string($this->master_field)) {
                if (!$this->getOwner()->hasField($this->master_field)) {
                    $owner = $this->hasJoin() ? $this->getJoin() : $this->getOwner();

                    $field = $owner->addField($this->master_field, ['system' => true, 'read_only' => true]);

                    $this->master_field = $field->elementId;
                }
            }

            $this->onHookShortToOwner(Model::HOOK_BEFORE_INSERT, \Closure::fromCallable([$this, 'beforeInsert']), [], -5);
            $this->onHookShortToOwner(Model::HOOK_BEFORE_UPDATE, \Closure::fromCallable([$this, 'beforeUpdate']));
            $this->onHookShortToOwner(Model::HOOK_AFTER_DELETE, \Closure::fromCallable([$this, 'doDelete']));
            $this->onHookShortToOwner(Model::HOOK_AFTER_LOAD, \Closure::fromCallable([$this, 'afterLoad']));
        }
    }

    /**
     * Returns DSQL query.
     */
    public function statement(): Persistence\Sql\Statement
    {
        $statement = $this->getPersistence()->query($this->getOwner())->getStatement(); // @phpstan-ignore-line

        return $statement->reset('table')->table($this->foreign_table, $this->foreign_alias);
    }

    /**
     * Before query is executed, this method will be called.
     */
    public function initSelectQuery(Query $query): void
    {
        // if ON is set, we don't have to worry about anything
        if ($this->on) {
            $query->getStatement()->join(
                $this->foreign_table,
                $this->on instanceof Expressionable ? $this->on : $this->getOwner()->expr($this->on),
                $this->kind,
                $this->foreign_alias
            );

            return;
        }

        $query->getStatement()->join(
            $this->foreign_table,
            $this->getOwner()->expr('{{}}.{} = {}', [
                ($this->foreign_alias ?: $this->foreign_table),
                $this->foreign_field,
                $this->getOwner()->getField($this->master_field),
            ]),
            $this->kind,
            $this->foreign_alias
        );

        /*
        if ($this->reverse) {
            $query->field([$this->elementId => ($this->join ?:
                (
                    ($model->table_alias ?: $model->table)
                    .'.'.$this->master_field)
            )]);
        } else {
            $query->field([$this->elementId => $this->foreign_alias.'.'.$this->foreign_field]);
        }
         */
    }

    /**
     * Called from afterLoad hook.
     */
    public function afterLoad(): void
    {
        $model = $this->getOwner();

        // we need to collect ID
        if ($model->getEntity()->isLoaded($this->elementId)) {
            $this->id = $model->getEntity()->get($this->elementId);
            $model->getEntity()->unset($this->elementId);
        }
    }

    /**
     * Called from beforeInsert hook.
     */
    public function beforeInsert(array &$data): void
    {
        if ($this->weak) {
            return;
        }

        $model = $this->getOwner();

        // The value for the master_field is set, so we are going to use existing record anyway
        if ($model->hasField($this->master_field) && $model->get($this->master_field)) {
            return;
        }

        $query = $this->statement()
            ->insert()
            ->set($model->persistence->encodeRow($model, $this->save_buffer));

        $this->save_buffer = [];
        $query
            ->set($this->foreign_field, null)
            ->execute();

        $this->id = $model->persistence->lastInsertId($model);

        if ($this->hasJoin()) {
            $this->getJoin()->set($this->master_field, $this->id);
        } else {
            $data[$this->master_field] = $this->id;
        }
    }

    /**
     * Called from afterInsert hook.
     *
     * @param mixed $id
     */
    public function afterInsert($id): void
    {
        if ($this->weak) {
            return;
        }

        $model = $this->getOwner();

        $query = $this->statement()
            ->insert()
            ->set($model->persistence->encodeRow($model, $this->save_buffer));

        $this->save_buffer = [];

        $query
            ->set($this->foreign_field, $this->hasJoin() ? $this->getJoin()->id : $id)
            ->execute();

        $this->id = $model->persistence->lastInsertId($model);
    }

    /**
     * Called from beforeUpdate hook.
     */
    public function beforeUpdate(array &$data): void
    {
        if ($this->weak) {
            return;
        }

        if (!$this->save_buffer) {
            return;
        }

        $model = $this->getOwner();
        $query = $this->statement()
            ->update()
            ->set($model->persistence->encodeRow($model, $this->save_buffer));

        $this->save_buffer = [];

        $id = $this->reverse ? $model->getId() : $model->get($this->master_field);

        $query
            ->where($this->foreign_field, $id)
            ->execute();
    }

    /**
     * Called from beforeDelete and afterDelete hooks.
     *
     * @param mixed $id
     */
    public function doDelete($id): void
    {
        if ($this->weak) {
            return;
        }

        $model = $this->getOwner();

        $id = $this->reverse ? $model->getId() : $model->get($this->master_field);

        $this->statement()->delete()->where($this->foreign_field, $id)->execute();
    }
}
