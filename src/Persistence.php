<?php

declare(strict_types=1);

namespace Phlex\Data;

use Phlex\Core\Factory;

abstract class Persistence implements MutatorInterface
{
    use MutatorTrait;
    use \Phlex\Core\ContainerTrait {
        add as _add;
    }
    use \Phlex\Core\DynamicMethodTrait;
    use \Phlex\Core\HookTrait;
    use \Phlex\Core\InjectableTrait;
    use \Phlex\Core\NameTrait;

    /** @const string */
    public const HOOK_AFTER_ADD = self::class . '@afterAdd';

    /** @const string */
    public const ID_LOAD_ONE = self::class . '@idLoadOne';
    /** @const string */
    public const ID_LOAD_ANY = self::class . '@idLoadAny';

    /**
     * Stores class default codec resolution array.
     *
     * @var array|null
     */
    protected static $defaultCodecs = [
        [Persistence\Codec::class],
    ];

    /**
     * Associate model with the data driver.
     */
    public function add(Model $model, array $defaults = []): Model
    {
        $model = Factory::factory($model, $defaults);

        if ($model->persistence) {
            if ($model->persistence === $this) {
                return $model;
            }

            throw new Exception('Model is already related to another persistence');
        }

        $model->persistence = $this;
        $this->initPersistence($model);
        $model = $this->_add($model);

        $this->hook(self::HOOK_AFTER_ADD, [$model]);

        return $model;
    }

    /**
     * Extend this method to enhance model to work with your persistence. Here
     * you can define additional methods or store additional data. This method
     * is executed before Model::doInitialize().
     */
    protected function initPersistence(Model $model)
    {
    }

    abstract public function query(Model $model): Persistence\Query;

    /**
     * Atomic executes operations within one begin/end transaction. Not all
     * persistences will support atomic operations, so by default we just
     * don't do anything.
     *
     * @return mixed
     */
    public function atomic(\Closure $fx)
    {
        return $fx();
    }

    public function getRow(Model $model, $id = null)
    {
        $query = $this->query($model);

        if ($id !== null) {
            $query->whereId($id);
        }

        $rawData = $query->getRow();

        if ($rawData === null) {
            throw new Model\RecordNotFoundException();
        }

        return $this->decodeRow($model, $rawData);
    }

    /**
     * Inserts record in database and returns new record ID.
     *
     * @return mixed
     */
    public function insert(Model $model, array $data)
    {
        // don't set id field at all if it's NULL
        if ($model->primaryKey && !isset($data[$model->primaryKey])) {
            unset($data[$model->primaryKey]);

            $this->syncIdSequence($model);
        }

        $data = $this->encodeRow($model, $data);

        $this->query($model)->insert($data)->execute();

        if ($model->primaryKey && isset($data[$model->primaryKey])) {
            $id = (string) $data[$model->primaryKey];

            $this->syncIdSequence($model);
        } else {
            $id = $this->lastInsertId($model);
        }

        return $id;
    }

    /**
     * Updates record in database.
     *
     * @param mixed $id
     */
    public function update(Model $model, $id, array $data)
    {
        $data = $this->encodeRow($model, $data);

        $model->onHook(Persistence\Query::HOOK_AFTER_UPDATE, function (Model $model, Persistence\Query $query, $result) use ($data) {
            if ($model->primaryKey && isset($data[$model->primaryKey]) && $model->getDirtyRef()[$model->primaryKey]) {
                // ID was changed
                $model->id = $data[$model->primaryKey];
            }
        }, [], -1000);

        return $this->query($model)->whereId($id)->update($data)->execute();
    }

    /**
     * Deletes record from database.
     *
     * @param mixed $id
     */
    public function delete(Model $model, $id)
    {
        $this->query($model)->whereId($id)->delete()->execute();
    }

    /**
     * Export all DataSet.
     *
     * @param bool $decode Should we decode exported data
     */
    public function export(Model $model, array $fields = null, bool $decode = true): array
    {
        $data = $this->query($model)->select($fields)->getRows();

        if ($decode) {
            $data = array_map(fn ($row) => $this->decodeRow($model, $row), $data);
        }

        return $data;
    }

    abstract public function lastInsertId(Model $model = null): string;

    protected function syncIdSequence(Model $model): void
    {
    }
}
