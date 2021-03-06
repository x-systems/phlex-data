<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql;

use Doctrine\DBAL;
use Phlex\Core\InjectableTrait;
use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

class Migration
{
    use InjectableTrait;

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
        $codec = $field->getCodec($this->persistence);

        if (!$codec instanceof Persistence\Sql\Codec) {
            throw new Exception('Only fields with Persistence\Sql\Codec can be migrated to Persistence\Sql');
        }

        return $codec->migrate($this);
    }
}
