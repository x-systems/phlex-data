<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Array_;

use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

/**
 * Provides model joining functionality specific for the Array_ persistence.
 *
 * @property Persistence\Array_|null $persistence
 */
class Join extends Model\Join
{
    /**
     * This method is to figure out stuff.
     */
    protected function doInitialize(): void
    {
        parent::doInitialize();

        // If kind is not specified, figure out join type
        if (!isset($this->kind)) {
            $this->kind = $this->weak ? 'left' : 'inner';
        }

        // Add necessary hooks
        if ($this->reverse) {
            $this->onHookShortToOwner(Model::HOOK_AFTER_INSERT, \Closure::fromCallable([$this, 'afterInsert']), [], -5);
            $this->onHookShortToOwner(Model::HOOK_BEFORE_UPDATE, \Closure::fromCallable([$this, 'beforeUpdate']), [], -5);
            $this->onHookShortToOwner(Model::HOOK_BEFORE_DELETE, \Closure::fromCallable([$this, 'doDelete']), [], -5);
        } else {
            $this->onHookShortToOwner(Model::HOOK_BEFORE_INSERT, \Closure::fromCallable([$this, 'beforeInsert']));
            $this->onHookShortToOwner(Model::HOOK_BEFORE_UPDATE, \Closure::fromCallable([$this, 'beforeUpdate']));
            $this->onHookShortToOwner(Model::HOOK_AFTER_DELETE, \Closure::fromCallable([$this, 'doDelete']));
            $this->onHookShortToOwner(Model::HOOK_AFTER_LOAD, \Closure::fromCallable([$this, 'afterLoad']));
        }
    }

    /**
     * Called from afterLoad hook.
     */
    public function afterLoad(): void
    {
        $model = $this->getOwner();

        // we need to collect ID
        $this->id = $model->data[$this->master_field];
        if (!$this->id) {
            return;
        }

        $data = $this->getPersistence()->getRow($this->getJoinModel(), $this->id);

        if (!$data) {
            throw (new Exception('Unable to load joined record'))
                ->addMoreInfo('table', $this->foreign_table)
                ->addMoreInfo('id', $this->id);
        }

        $model->data = array_merge($data, $model->data);
    }

    /**
     * Called from beforeInsert hook.
     */
    public function beforeInsert(array &$data): void
    {
        if ($this->weak) {
            return;
        }

        if ($this->getOwner()->hasField($this->master_field) && $this->getOwner()->get($this->master_field)) {
            // The value for the master_field is set,
            // we are going to use existing record.
            return;
        }

        // Figure out where are we going to save data
        $persistence = $this->persistence ?: $this->getOwner()->persistence;

        $this->id = $persistence->insert(
            $this->getJoinModel(),
            $this->save_buffer
        );

        $data[$this->master_field] = $this->id;

        //$this->getOwner()->set($this->master_field, $this->id);
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

        $this->save_buffer[$this->foreign_field] = $this->hasJoin() ? $this->getJoin()->id : $id;

        $persistence = $this->persistence ?: $this->getOwner()->persistence;

        $this->id = $persistence->insert(
            $this->getJoinModel(),
            $this->save_buffer
        );
    }

    /**
     * Called from beforeUpdate hook.
     */
    public function beforeUpdate(array &$data): void
    {
        if ($this->weak) {
            return;
        }

        $persistence = $this->persistence ?: $this->getOwner()->persistence;

        $this->id = $persistence->update(
            $this->getJoinModel(),
            $this->id,
            $this->save_buffer
        );
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

        $persistence = $this->persistence ?: $this->getOwner()->persistence;

        $persistence->delete(
            $this->getJoinModel(),
            $this->id
        );

        $this->id = null;
    }

    protected function getPersistence()
    {
        return $this->persistence ?: $this->getOwner()->persistence;
    }

    protected function getJoinModel()
    {
        $joinModel = clone $this->getOwner();
        $joinModel->table = $this->foreign_table;

        return $joinModel;
    }
}
