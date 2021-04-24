<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence;

use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

/**
 * Implements persistence driver that can save data into array and load
 * from array. This basic driver only offers the load/save support based
 * around ID, you can't use conditions, order or limit.
 */
class Array_ extends Persistence
{
    /** @var array */
    public $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Array of last inserted ids per table.
     * Last inserted ID for any table is stored under '$' key.
     *
     * @var array
     */
    protected $lastInsertIds = [];

    public function getRawDataIterator(Model $model): \Iterator
    {
        return (function ($iterator) use ($model) {
            foreach ($iterator as $id => $row) {
                yield $id => $this->getRowWithId($model, $row, $id);
            }
        })(new \ArrayIterator($this->data[$model->table]));
    }

    public function setRawData(Model $model, array $row, $id = null)
    {
        $row = $this->getRowWithId($model, $row, $id);

        $id = $id ?? $this->lastInsertId($model);

        if ($model->primaryKey) {
            $primaryKeyColumnName = $model->getPrimaryKeyField()->getPersistenceName();

            unset($row[$primaryKeyColumnName]);
        }

        $this->data[$model->table][$id] = $row; //array_intersect_key($row, $rowWithId);

        return $id;
    }

    public function unsetRawData(string $table, $id)
    {
        unset($this->data[$table][$id]);
    }

    private function getRowWithId(Model $model, array $row, $id = null)
    {
        if ($id === null) {
            $id = $this->generateNewId($model);
        }

        if ($model->primaryKey) {
            $primaryKeyColumnName = $model->getPrimaryKeyField()->getPersistenceName();

            if (array_key_exists($primaryKeyColumnName, $row)) {
                $this->assertNoIdMismatch($row[$primaryKeyColumnName], $id);
                unset($row[$primaryKeyColumnName]);
            }

            // typecastSave value so we can use strict comparison
            $row = [$primaryKeyColumnName => $this->typecastSaveField($model->getPrimaryKeyField(), $id)] + $row;
        }

        return $row;
    }

//     /**
//      * @deprecated TODO temporary for these:
//      *             - https://github.com/x-systems/phlex-data/blob/90ab68ac063b8fc2c72dcd66115f1bd3f70a3a92/src/Reference/ContainsOne.php#L119
//      *             - https://github.com/x-systems/phlex-data/blob/90ab68ac063b8fc2c72dcd66115f1bd3f70a3a92/src/Reference/ContainsMany.php#L66
//      *             remove once fixed/no longer needed
//      */
//     public function getRawDataByTable(Model $model, string $table): array
//     {
//         $rows = [];
//         foreach ($this->data[$table] as $id => $row) {
//             $this->addIdToLoadRow($model, $row, $id);
//             $rows[$id] = $row;
//         }

//         return $rows;
//     }

    private function assertNoIdMismatch($idFromRow, $id): void
    {
        if ($idFromRow !== null && (is_int($idFromRow) ? (string) $idFromRow : $idFromRow) !== (is_int($id) ? (string) $id : $id)) {
            throw (new Exception('Row contains ID column, but it does not match the row ID'))
                ->addMoreInfo('idFromKey', $id)
                ->addMoreInfo('idFromData', $idFromRow);
        }
    }

    public function typecastSaveRow(Model $model, array $row): array
    {
        $sqlPersistence = (new \ReflectionClass(Sql::class))->newInstanceWithoutConstructor();

        return $sqlPersistence->typecastSaveRow($model, $row);
    }

    public function typecastLoadRow(Model $model, array $row): array
    {
        $sqlPersistence = (new \ReflectionClass(Sql::class))->newInstanceWithoutConstructor();

        return $sqlPersistence->typecastLoadRow($model, $row);
    }

    /**
     * {@inheritdoc}
     */
    public function add(Model $model, array $defaults = []): Model
    {
        if (isset($defaults[0])) {
            $model->table = $defaults[0];
            unset($defaults[0]);
        }

        $defaults = array_merge([
            '_default_seed_join' => [Array_\Join::class],
        ], $defaults);

        $model = parent::add($model, $defaults);

        if ($model->primaryKey && $model->hasField($model->primaryKey)) {
            $f = $model->getPrimaryKeyField();
            if (!$f->type) {
                $f->type = 'integer';
            }
        }

        // if there is no model table specified, then create fake one named 'data'
        // and put all persistence data in there
        if (!$model->table) {
            $model->table = 'data'; // fake table name 'data'
            if (!isset($this->data[$model->table]) || count($this->data) !== 1) {
                $this->data = [$model->table => $this->data];
            }
        }

        // if there is no such table in persistence, then create empty one
        if (!isset($this->data[$model->table])) {
            $this->data[$model->table] = [];
        }

        return $model;
    }

    /**
     * Generates new record ID.
     *
     * @return string
     */
    public function generateNewId(Model $model, string $table = null)
    {
        $table = $table ?? $model->table;

        $type = $model->primaryKey ? get_class($model->getField($model->primaryKey)->getType()) : Model\Field\Type\Integer::class;

        switch ($type) {
            case Model\Field\Type\Integer::class:
                $ids = $model->primaryKey ? array_keys($this->data[$table]) : [count($this->data[$table])];

                $id = $ids ? max($ids) + 1 : 1;

                break;
            case Model\Field\Type\Line::class:
                $id = uniqid();

                break;
            default:
                throw (new Exception('Unsupported id field type. Array supports type=integer or type=string only'))
                    ->addMoreInfo('type', $type);
        }

        $this->lastInsertIds[$table] = $id;
        $this->lastInsertIds['$'] = $id;

        return $id;
    }

    /**
     * Last ID inserted.
     * Last inserted ID for any table is stored under '$' key.
     *
     * @return mixed
     */
    public function lastInsertId(Model $model = null): string
    {
        if ($model) {
            return (string) $this->lastInsertIds[$model->table] ?? null;
        }

        return (string) $this->lastInsertIds['$'] ?? null;
    }

    public function query(Model $model): Persistence\Query
    {
        return new Array_\Query($model, $this);
    }
}
