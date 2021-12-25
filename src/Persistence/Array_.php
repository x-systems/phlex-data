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
    protected $modelDefaults = [
        'joinSeed' => [Array_\Join::class],
    ];

    /** @var array */
    public $data;

    /** @var array<string, int> */
    protected $autoIncrement = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;

        foreach ($data as $table => $rows) {
            $this->autoIncrement[$table] = $rows ? max(array_keys($rows)) : 0;
        }
    }

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

        $id ??= $this->lastInsertId($model);

        if ($model->primaryKey) {
            $primaryKeyColumnName = $model->getPrimaryKeyField()->getCodec($this)->getKey();

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

        if ($id > ($this->autoIncrement[$model->table] ?? 0)) {
            $this->autoIncrement[$model->table] = $id;
        }

        if ($model->primaryKey) {
            $primaryKeyField = $model->getPrimaryKeyField();
            $primaryKeyColumnName = $primaryKeyField->getCodec($this)->getKey();

            if (array_key_exists($primaryKeyColumnName, $row)) {
                $this->assertNoIdMismatch($row[$primaryKeyColumnName], $id);
                unset($row[$primaryKeyColumnName]);
            }

            // encode value so we can use strict comparison
            $row = [$primaryKeyColumnName => $primaryKeyField->getCodec($this)->encode($id)] + $row;
        }

        return $row;
    }

    private function assertNoIdMismatch($idFromRow, $id): void
    {
        if ($idFromRow !== null && (is_int($idFromRow) ? (string) $idFromRow : $idFromRow) !== (is_int($id) ? (string) $id : $id)) {
            throw (new Exception('Row contains ID column, but it does not match the row ID'))
                ->addMoreInfo('idFromKey', $id)
                ->addMoreInfo('idFromData', $idFromRow);
        }
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

        $model = parent::add($model, $defaults);

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
        $table ??= $model->table;

        $type = $model->primaryKey ? get_class($model->getPrimaryKeyField()->getValueType()) : Model\Field\Type\Integer::class;

        switch ($type) {
            case Model\Field\Type\Integer::class:
                $nextId = ($this->autoIncrement[$table] ?? 0) + 1;

                break;
            case Model\Field\Type\String_::class:
                $nextId = uniqid();

                break;
            default:
                throw (new Exception('Unsupported id field type. Array supports type=integer or type=string only'))
                    ->addMoreInfo('type', $type);
        }

        $this->autoIncrement[$table] = $nextId;
        $this->autoIncrement['$'] = $nextId;

        return $nextId;
    }

    /**
     * Last ID inserted.
     * Last inserted ID for any table is stored under '$' key.
     */
    public function lastInsertId(Model $model = null): string
    {
        if ($model) {
            return (string) ($this->autoIncrement[$model->table] ?? null);
        }

        return (string) ($this->autoIncrement['$'] ?? null);
    }

    public function query(Model $model): Persistence\Query
    {
        return new Array_\Query($model);
    }
}
