<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\SQL;

use Atk4\Dsql\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
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
//     public const REF_TYPE_NONE = 0;
//     public const REF_TYPE_LINK = 1;
//     public const REF_TYPE_PRIMARY = 2;

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
        } elseif ($source instanceof Persistence\SQL) {
            $this->connection = $source->connection;
        } elseif ($source instanceof Model && $source->persistence instanceof Persistence\SQL) {
            $this->connection = $source->persistence->connection;
        } else {
            throw (new Exception('Source is specified incorrectly. Must be Connection, Persistence or initialized Model'))
                ->addMoreInfo('source', $source);
        }

        if ($source instanceof Model && $source->persistence instanceof Persistence\SQL) {
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
        } catch (\Doctrine\DBAL\Exception | \Doctrine\DBAL\DBALException $e) { // @phpstan-ignore-line for DBAL 2.x
        }

        return $this;
    }

//     public function field(string $fieldName, $options = []): self
//     {
//         // TODO remove once we no longer support "money" database type
//         if (($options['type'] ?? null) === 'money') {
//             $options['type'] = 'float';
//         }

//         $refType = $options['ref_type'] ?? self::REF_TYPE_NONE;
//         unset($options['ref_type']);

//         $column = $this->table->addColumn($this->getDatabasePlatform()->quoteSingleIdentifier($fieldName), $options['type'] ?? 'string');

//         if (!($options['mandatory'] ?? false) && $refType !== self::REF_TYPE_PRIMARY) {
//             $column->setNotnull(false);
//         }

//         if ($column->getType()->getName() === 'integer' && $refType !== self::REF_TYPE_NONE) {
//             $column->setUnsigned(true);
//         }

//         if (in_array($column->getType()->getName(), ['string', 'text'], true)) {
//             if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
//                 $column->setPlatformOption('collation', 'NOCASE');
//             }
//         }

//         if ($refType === self::REF_TYPE_PRIMARY) {
//             $this->table->setPrimaryKey([$this->getDatabasePlatform()->quoteSingleIdentifier($fieldName)]);
//             if (!$this->getDatabasePlatform() instanceof OraclePlatform) {
//                 $column->setAutoincrement(true);
//             }
//         }

//         return $this;
//     }

    public function id(string $name = 'id'): self
    {
        $options = [
            'type' => 'integer',
            'ref_type' => self::REF_TYPE_PRIMARY,
        ];

        $this->field($name, $options);

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

//             if ($field->short_name === $model->primaryKey) {
//                 $refype = self::REF_TYPE_PRIMARY;
//                 $persistField = $field;
//             } else {
//                 $refField = $this->getReferenceField($field);
//                 $refype = $refField !== null ? self::REF_TYPE_LINK : $refype = self::REF_TYPE_NONE;
//                 $persistField = $refField ?? $field;
//             }

//             $options = [
//                 'type' => $refype !== self::REF_TYPE_NONE && empty($persistField->type) ? 'integer' : $persistField->type,
//                 'ref_type' => $refype,
//                 'mandatory' => ($field->mandatory || $field->required) && ($persistField->mandatory || $persistField->required),
//             ];

//             $this->field($field->actual ?: $field->short_name, $options);
        }

        return $model;
    }

    public function addColumn(Model\Field $field): Column
    {
        return $field->getPersistenceCodec()->migrate($this);
    }

    protected function getReferenceField(Field $field): ?Field
    {
        $reference = $field->getReference();
        if ($reference instanceof Model\Reference\HasOne) {
            $referenceTheirField = \Closure::bind(function () use ($reference) {
                return $reference->their_field;
            }, null, Model\Reference::class)();

            $referenceField = $referenceTheirField ?? $reference->getOwner()->id_field;

            $modelSeed = is_array($reference->model)
            ? $reference->model
            : [get_class($reference->model)];
            $referenceModel = Model::fromSeed($modelSeed, [new Persistence\SQL($this->connection)]);

            return $referenceModel->getField($referenceField);
        }

        return null;
    }
}
