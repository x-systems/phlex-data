<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\SQL;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Types;
use Phlex\Data\Persistence;

class Codec extends Persistence\Codec
{
    /**
     * DBAL column type to use for storage.
     *
     * @var string
     */
    protected $columnTypeName = Types::STRING;

    public function getColumnTypeName(): string
    {
        return $this->columnTypeName;
    }

    protected function doEncode($value)
    {
        return (string) $value;
    }

    protected function isEncodable($value): bool
    {
        return parent::isEncodable($value)
            && !$value instanceof \Atk4\Dsql\Expression
            && !$value instanceof \Atk4\Dsql\Expressionable;
    }

    public function migrate(Migration $migrator): Column
    {
        $columnNameIdentifier = $migrator->getDatabasePlatform()->quoteSingleIdentifier($this->field->getPersistenceName());

        $column = $migrator->table->addColumn($columnNameIdentifier, $this->getColumnTypeName());

        if ($this->field->isPrimaryKey()) {
            $migrator->table->setPrimaryKey([$columnNameIdentifier]);

            $column->setAutoincrement(true);
        } elseif (!($this->field->mandatory ?? false)) {
            $column->setNotnull(false);
        }

        return $column;
    }
}
