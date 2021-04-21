<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Field;

use Phlex\Data\Persistence\Codec as BaseCodec;
use Phlex\Data\Persistence\Sql;

class Codec extends BaseCodec
{
    /**
     * DBAL column type to use for storage.
     *
     * @var string
     */
    protected $columnTypeName = 'string';

    public function migrate(Sql\Migration $migrator, Sql\Field $field)
    {
        $column = $migrator->table->addColumn($migrator->getDatabasePlatform()->quoteSingleIdentifier($field->short_name), $this->columnTypeName);

        if (!($options['mandatory'] ?? false) && $refType !== self::REF_TYPE_PRIMARY) {
            $column->setNotnull(false);
        }

        if ($column->getType()->getName() === 'integer' && $refType !== self::REF_TYPE_NONE) {
            $column->setUnsigned(true);
        }

        if (in_array($column->getType()->getName(), ['string', 'text'], true)) {
            if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
                $column->setPlatformOption('collation', 'NOCASE');
            }
        }

        if ($refType === self::REF_TYPE_PRIMARY) {
            $this->table->setPrimaryKey([$this->getDatabasePlatform()->quoteSingleIdentifier($fieldName)]);
            if (!$this->getDatabasePlatform() instanceof OraclePlatform) {
                $column->setAutoincrement(true);
            }
        }
    }
}
