<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform;

use Doctrine\DBAL;
use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

class Oracle extends Persistence\Sql
{
    public $_default_seed_statement = [Oracle\Statement::class];

    public $_default_seed_migration = [Oracle\Migration::class];

    protected static $defaultCodecs = [
        Model\Field\Type\Object_::class => [Oracle\Codec\Object_::class],
        Model\Field\Type\Array_::class => [Oracle\Codec\Array_::class],
    ];

    public function groupConcat($field, string $delimiter = ','): Persistence\Sql\Expression
    {
        return $this->expr('listagg({field}, []) within group (order by {field})', ['field' => $field, $delimiter]);
    }

    protected function createConnection(array $dsn): DBAL\Connection
    {
        $connection = parent::createConnection($dsn);

        // Oracle CLOB/BLOB has limited SQL support, see:
        // https://stackoverflow.com/questions/12980038/ora-00932-inconsistent-datatypes-expected-got-clob#12980560
        // fix this Oracle inconsistency by using VARCHAR/VARBINARY instead (but limited to 4000 bytes)
        \Closure::bind(function () use ($connection) {
            $connection->platform = new class() extends DBAL\Platforms\OraclePlatform {
                private function forwardTypeDeclarationSQL(string $targetMethodName, array $column): string
                {
                    $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS);
                    foreach ($backtrace as $frame) {
                        if ($this === ($frame['object'] ?? null)
                                && $targetMethodName === ($frame['function'] ?? null)) {
                            throw new Exception('Long CLOB/TEXT (4000+ bytes) is not supported for Oracle');
                        }
                    }

                    return $this->{$targetMethodName}($column);
                }

                public function getClobTypeDeclarationSQL(array $column)
                {
                    $column['length'] = $this->getVarcharMaxLength();

                    return $this->forwardTypeDeclarationSQL('getVarcharTypeDeclarationSQL', $column);
                }

                public function getBlobTypeDeclarationSQL(array $column)
                {
                    $column['length'] = $this->getBinaryMaxLength();

                    return $this->forwardTypeDeclarationSQL('getBinaryTypeDeclarationSQL', $column);
                }
            };
        }, null, DBAL\Connection::class)();

        return $connection;
    }

    public function query(Model $model = null): Persistence\Query
    {
        return new Oracle\Query($model);
    }

    public function lastInsertId(Model $model = null): string
    {
        // TODO: Oracle does not support lastInsertId(), only for testing
        // as this does not support concurrent inserts
        if (!$model->hasPrimaryKeyField()) {
            return ''; // TODO code should never call lastInsertId() if id field is not defined
        }

        return $this->statement()
            ->table($model->table)
            ->field($this->expr('max({id_col})', ['id_col' => $model->primaryKey]), 'max_id')
            ->execute()
            ->fetchOne();
    }
}
