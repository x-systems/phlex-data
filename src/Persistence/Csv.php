<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence;

use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

/**
 * Implements persistence driver that can save data and load from CSV file.
 * This basic driver only offers the load/save. It does not offer conditions or
 * id-specific operations. You can only use a single persistence object with
 * a single file.
 *
 * $p = new Persistence\Csv('file.csv');
 * $m = new MyModel($p);
 * $data = $m->export();
 *
 * Alternatively you can write into a file. First operation you perform on
 * the persistence will determine the mode.
 *
 * $p = new Persistence\Csv('file.csv');
 * $m = new MyModel($p);
 * $m->import($data);
 */
class Csv extends Persistence
{
    /**
     * Name of the file or file object.
     *
     * @var string|\SplFileObject
     */
    public $file;

    /**
     * Line in CSV file.
     *
     * @var int
     */
    public $line = 0;

    /**
     * Delimiter in CSV file.
     *
     * @var string
     */
    public $delimiter = ',';

    /**
     * Enclosure in CSV file.
     *
     * @var string
     */
    public $enclosure = '"';

    /**
     * Escape character in CSV file.
     *
     * @var string
     */
    public $escape_char = '\\';

    /**
     * File access object.
     *
     * @var \SplFileObject
     */
    protected $fileObject;

    protected $lastInsertId;

    public function __construct($file, array $defaults = [])
    {
        $this->file = $file;
        $this->setDefaults($defaults);
    }

    protected function initPersistence(Model $model)
    {
        parent::initPersistence($model);

        $this->initFileObject($model);
    }

    public function getRawDataIterator(Model $model): \Iterator
    {
        $keys = $this->getFileHeader();

        return (function ($iterator) use ($model, $keys) {
            foreach ($iterator as $id => $row) {
                if ($row) {
                    yield $id - 1 => $this->getRowWithId($model, array_combine($keys, $row), $id);
                }
            }
        })(new \LimitIterator($this->fileObject, 1));
    }

    public function setRawData(Model $model, $row, $id = null)
    {
        if (!$this->getFileHeader()) {
            $this->initFileHeader($model);
        }

        $emptyRow = array_flip($this->getFileHeader());

        $row = array_intersect_key(array_merge($emptyRow, $this->getRowWithId($model, $row, $id)), $emptyRow);

        $id = $id ?? $this->lastInsertId;

        $this->fileObject->seek($id);

        $this->fileObject->fputcsv($row);

        return $id;
    }

    private function getRowWithId(Model $model, array $row, $id = null)
    {
        if ($id === null) {
            $id = $this->generateNewId($model);
        }

        if ($model->primaryKey) {
            $primaryKeyField = $model->getPrimaryKeyField();
            $primaryKeyColumnName = $primaryKeyField->getPersistenceName();

            if (array_key_exists($primaryKeyColumnName, $row)) {
                $this->assertNoIdMismatch($row[$primaryKeyColumnName], $id);
                unset($row[$primaryKeyColumnName]);
            }

            // typecastSave value so we can use strict comparison
            $row = [$primaryKeyColumnName => $primaryKeyField->encodePersistenceValue($id)] + $row;
        }

        return $row;
    }

    private function assertNoIdMismatch($idFromRow, $id): void
    {
        if ($idFromRow !== null && (is_int($idFromRow) ? (string) $idFromRow : $idFromRow) !== (is_int($id) ? (string) $id : $id)) {
            throw (new Exception('Row constains ID column, but it does not match the row ID'))
                ->addMoreInfo('idFromKey', $id)
                ->addMoreInfo('idFromData', $idFromRow);
        }
    }

    protected function initFileObject(Model $model)
    {
        if (is_string($this->file)) {
            if (!file_exists($this->file)) {
                file_put_contents($this->file, '');
            }

            $this->fileObject = new \SplFileObject($this->file, 'r+');
        } elseif ($this->file instanceof \SplFileObject) {
            $this->fileObject = $this->file;
        }

        $this->fileObject->setFlags(
            \SplFileObject::READ_CSV |
            \SplFileObject::SKIP_EMPTY |
            \SplFileObject::DROP_NEW_LINE
        );

        // see https://bugs.php.net/bug.php?id=65601
        if (PHP_MAJOR_VERSION < 8) {
            $this->fileObject->setFlags($this->fileObject->getFlags() | \SplFileObject::READ_AHEAD);
        }

        $this->fileObject->setCsvControl($this->delimiter, $this->enclosure, $this->escape_char);
    }

    protected function initFileHeader(Model $model): void
    {
        $this->executeRestoringPointer(function () use ($model) {
            $this->fileObject->seek(0);

            $this->fileObject->fputcsv(array_keys($model->getFields('not system')));
        });
    }

    public function getFileHeader(): array
    {
        $header = $this->executeRestoringPointer(function () {
            $this->fileObject->seek(0);

            return $this->fileObject->current();
        });

        return array_map(function ($name) {
            return preg_replace('/[^a-z0-9_-]+/i', '_', $name);
        }, $header ?: []);
    }

    private function executeRestoringPointer(\Closure $fx, array $args = [])
    {
        $position = $this->fileObject->key();

        $result = $fx(...$args);

        $this->fileObject->seek($position);

        return $result;
    }

    /**
     * Deletes record in data array.
     *
     * @param mixed $id
     */
    public function delete(Model $model, $id, string $table = null)
    {
        throw new Exception('Deleting records is not supported in CSV persistence.');
    }

    public function lastInsertId(Model $model = null): string
    {
        return $this->lastInsertId;
    }

    public function query(Model $model): Persistence\Query
    {
        return new Csv\Query($model);
    }

    public function generateNewId(Model $model)
    {
        while (!$this->fileObject->eof()) {
            $this->fileObject->next();
        }

        $this->lastInsertId = $this->fileObject->key();

        return $this->lastInsertId;
    }
}
