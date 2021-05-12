<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql;

use Doctrine\DBAL;
use Phlex\Core\DiContainerTrait;
use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

class Migration
{
    use DiContainerTrait;

    /** @var Persistence\Sql */
    public $persistence;

    /** @var DBAL\Schema\Table */
    public $table;

    /**
     * Create new migration.
     *
     * @param DBAL\Connection|Persistence|Model $source
     */
    public function __construct($source = null)
    {
        if ($source !== null) {
            $this->setSource($source);
        }
    }

    protected function setSource($source)
    {
        if ($source instanceof Persistence\Sql) {
            $this->persistence = $source;
        } elseif ($source instanceof Model && $source->persistence instanceof Persistence\Sql) {
            $this->persistence = $source->persistence;
        } else {
            throw (new Exception('Source is specified incorrectly. Must be Connection, Persistence or initialized Model'))
                ->addMoreInfo('source', $source);
        }

        if ($source instanceof Model && $source->persistence instanceof Persistence\Sql) {
            $this->setModel($source);
        }
    }

    public function getDatabasePlatform(): DBAL\Platforms\AbstractPlatform
    {
        return $this->persistence->connection->getDatabasePlatform();
    }

    public function getSchemaManager(): DBAL\Schema\AbstractSchemaManager
    {
        return $this->persistence->connection->getSchemaManager();
    }

    public function table($tableName): self
    {
        $this->table = new DBAL\Schema\Table($this->getDatabasePlatform()->quoteSingleIdentifier($tableName));

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
        } catch (DBAL\Exception $e) {
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

    public function addColumn(Model\Field $field): DBAL\Schema\Column
    {
        $codec = $field->getPersistenceCodec();

        if (!$codec instanceof Persistence\Sql\Codec) {
            throw new Exception('Only fields with Persistence\Sql\Codec can be migrated to Persistence\Sql');
        }

        return $codec->migrate($this);
    }

    protected function getReferenceField(Model\Field $field): ?Model\Field
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
            $referenceModel = Model::fromSeed($modelSeed, [Persistence\Sql::createFromConnection($this->persistence->connection)]);

            return $referenceModel->getField($referenceField);
        }

        return null;
    }
}
