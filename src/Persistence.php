<?php

declare(strict_types=1);

namespace Phlex\Data;

use Phlex\Core\Factory;

abstract class Persistence
{
    use \Phlex\Core\ContainerTrait {
        add as _add;
    }
    use \Phlex\Core\DiContainerTrait;
    use \Phlex\Core\DynamicMethodTrait;
    use \Phlex\Core\HookTrait;
    use \Phlex\Core\NameTrait;

    /** @const string */
    public const HOOK_AFTER_ADD = self::class . '@afterAdd';

    /** @const string */
    public const ID_LOAD_ONE = self::class . '@idLoadOne';
    /** @const string */
    public const ID_LOAD_ANY = self::class . '@idLoadAny';

    protected $codecs = [];

    protected static $defaultCodecs = [
        [Persistence\Codec::class],
    ];

    public static function getDefaultCodecs()
    {
        $parentClass = get_parent_class(static::class);

        return static::$defaultCodecs + ($parentClass ? $parentClass::getDefaultCodecs() : []);
    }

    public function getCodecs()
    {
        return (array) $this->codecs + $this->getDefaultCodecs();
    }

    public function setCodecs(array $codecs)
    {
        $this->codecs = $codecs;

        return $this;
    }

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
            return null;
        }

        return $this->typecastLoadRow($model, $rawData);
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

        $data = $this->typecastSaveRow($model, $data);

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
        $data = $this->typecastSaveRow($model, $data);

        $model->onHook(Persistence\Query::HOOK_AFTER_UPDATE, function (Model $model, Persistence\Query $query, $result) use ($data) {
            if ($model->primaryKey && isset($data[$model->primaryKey]) && $model->dirty[$model->primaryKey]) {
                // ID was changed
                $model->id = $data[$model->primaryKey];
            }
        }, [], -1000);

        $result = $this->query($model)->whereId($id)->update($data)->execute();

        //@todo $result->rowCount() is specific to PDO, must be done specific to Query
        // if any rows were updated in database, and we had expressions, reload
        if ($model->reload_after_save === true /* && (!$result || iterator_count($result)) */) {
            $dirty = $model->dirty;
            $model->reload();
            $model->_dirty_after_reload = $model->dirty;
            $model->dirty = $dirty;
        }

        return $result;
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
     * @param bool $typecast Should we typecast exported data
     */
    public function export(Model $model, array $fields = null, bool $typecast = true): array
    {
        $data = $this->query($model)->select($fields)->getRows();

        if ($typecast) {
            $data = array_map(function ($row) use ($model) {
                return $this->typecastLoadRow($model, $row);
            }, $data);
        }

        return $data;
    }

    abstract public function lastInsertId(Model $model = null): string;

    protected function syncIdSequence(Model $model): void
    {
    }

    /**
     * Will convert one row of data from native PHP types into
     * persistence types. This will also take care of the "actual"
     * field keys. Example:.
     *
     * In:
     *  [
     *    'name'=>' John Smith',
     *    'age'=>30,
     *    'password'=>'abc',
     *    'is_married'=>true,
     *  ]
     *
     *  Out:
     *   [
     *     'first_name'=>'John Smith',
     *     'age'=>30,
     *     'is_married'=>1
     *   ]
     */
    public function typecastSaveRow(Model $model, array $row): array
    {
        $result = [];
        foreach ($row as $fieldName => $value) {
            // We have no knowledge of the field, it wasn't defined, so
            // we will leave it as-is.
            if (!$model->hasField($fieldName)) {
                $result[$fieldName] = $value;

                continue;
            }

            // Look up field object
            $field = $model->getField($fieldName);

            $result[$field->getPersistenceName()] = $field->encodePersistenceValue($value);
        }

        return $result;
    }

    /**
     * Will convert one row of data from Persistence-specific
     * types to PHP native types.
     *
     * NOTE: Please DO NOT perform "actual" field mapping here, because data
     * may be "aliased" from SQL persistences or mapped depending on persistence
     * driver.
     */
    public function typecastLoadRow(Model $model, array $row): array
    {
        $result = [];
        foreach ($row as $fieldName => $value) {
            // We have no knowledge of the field, it wasn't defined, so
            // we will leave it as-is.
            if (!$model->hasField($fieldName)) {
                $result[$fieldName] = $value;

                continue;
            }

            // Look up field object
            $field = $model->getField($fieldName);

            $result[$fieldName] = $field->decodePersistenceValue($value);
        }

        return $result;
    }
}
