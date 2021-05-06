<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql;

use Atk4\Dsql\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Phlex\Core\DiContainerTrait;
use Phlex\Core\Exception;
use Phlex\Data\Model;
use Phlex\Data\Model\Field;
use Phlex\Data\Persistence;

class Migration
{
    use DiContainerTrait;

    /** @var Connection */
    public $connection;

    /** @var Table */
    public $table;

    /**
     * Create new migration.
     *
     * @param Connection|Persistence|Model $source
     */
    public function __construct($source = null)
    {
        if ($source !== null) {
            $this->setSource($source);
        }
    }

    protected function setSource($source)
    {
        if ($source instanceof Connection) {
            $this->connection = $source;
        } elseif ($source instanceof Persistence\Sql) {
            $this->connection = $source->connection;
        } elseif ($source instanceof Model && $source->persistence instanceof Persistence\Sql) {
            $this->connection = $source->persistence->connection;
        } else {
            throw (new Exception('Source is specified incorrectly. Must be Connection, Persistence or initialized Model'))
                ->addMoreInfo('source', $source);
        }

        if ($source instanceof Model && $source->persistence instanceof Persistence\Sql) {
            $this->setModel($source);
        }
    }

    public function getDatabasePlatform(): AbstractPlatform
    {
        return $this->connection->getDatabasePlatform();
    }

    public function getSchemaManager(): AbstractSchemaManager
    {
        return $this->connection->connection()->getSchemaManager();
    }

    public function table($tableName): self
    {
        $this->table = new Table($this->getDatabasePlatform()->quoteSingleIdentifier($tableName));

        return $this;
    }

    public function create(): self
    {
        $this->getSchemaManager()->createTable($this->table);

        return $this;
    }

    public function drop(): self
    {
        $this->getSchemaManager()->dropTable($this->getDatabasePlatform()->quoteSingleIdentifier($this->table->getName()));

        return $this;
    }

    public function dropIfExists(): self
    {
        try {
            $this->drop();
        } catch (\Doctrine\DBAL\Exception $e) {
        }

        return $this;
    }

    public function setModel(Model $model): Model
    {
        $this->table($model->table);

        foreach ($model->getFields() as $field) {
            if (!$field->savesToPersistence()) {
                continue;
            }

            $this->addColumn($field);
        }

        return $model;
    }

    public function addColumn(Model\Field $field): Column
    {
        return $field->getPersistenceCodec()->migrate($this); // @phpstan-ignore-line
    }

    protected function getReferenceField(Field $field): ?Field
    {
        $reference = $field->getReference();
        if ($reference instanceof Model\Reference\HasOne) {
            $referenceTheirField = \Closure::bind(function () use ($reference) {
                return $reference->theirFieldName;
            }, null, Model\Reference::class)();

            $referenceField = $referenceTheirField ?? $reference->getOwner()->primaryKey;

            $modelSeed = is_array($reference->model)
                ? $reference->model
                : [get_class($reference->model)];
            $referenceModel = Model::fromSeed($modelSeed, [Persistence\Sql::createFromConnection($this->connection)]);

            return $referenceModel->getField($referenceField);
        }

        return null;
    }
}
