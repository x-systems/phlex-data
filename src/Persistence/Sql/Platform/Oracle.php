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

    public function __construct($connection, $user = null, $password = null, $options = [])
    {
        parent::__construct(...func_get_args());

        // date and datetime format should be like this for Phlex Data to correctly pick it up and typecast
        $this->expr('ALTER SESSION SET NLS_TIMESTAMP_FORMAT={datetime_format} NLS_DATE_FORMAT={date_format} NLS_NUMERIC_CHARACTERS={dec_char}', [
            'datetime_format' => 'YYYY-MM-DD HH24:MI:SS', // datetime format
            'date_format' => 'YYYY-MM-DD', // date format
            'dec_char' => '. ', // decimal separator, no thousands separator
        ])->execute();
    }

    /** @var int */
    private static $ciDifferentDsnCounter = 0;
    /** @var array */
    private static $ciLastConnectDsn;
    /** @var \PDO|null */
    private static $ciLastConnectPdo;

    protected function createConnection(array $dsn): DBAL\Connection
    {
        $dbalConnection = parent::createConnection($dsn);

        // Oracle CLOB/BLOB has limited SQL support, see:
        // https://stackoverflow.com/questions/12980038/ora-00932-inconsistent-datatypes-expected-got-clob#12980560
        // fix this Oracle inconsistency by using VARCHAR/VARBINARY instead (but limited to 4000 bytes)
        \Closure::bind(function () use ($dbalConnection) {
            $dbalConnection->platform = new class() extends DBAL\Platforms\OraclePlatform {
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

        // for some reasons, the following error:
        // PDOException: SQLSTATE[HY000]: pdo_oci_handle_factory: ORA-12516: TNS:listener could not find available handler with matching protocol stack
        // is shown randomly when a lot of connections are created in tests,
        // so for CI, fix this issue by reusing the previous PDO connection
        // TODO remove once phlex-data tests can be run consistently without errors
        if (class_exists(\PHPUnit\Framework\TestCase::class, false)) { // called from phpunit
            $notReusableFunc = function (string $message): void {
                echo "\n" . 'connection for CI can not be reused:' . "\n" . $message . "\n";
                self::$ciLastConnectPdo = null;
            };

            if (self::$ciLastConnectDsn !== $dsn) {
                ++self::$ciDifferentDsnCounter;
                if (self::$ciDifferentDsnCounter >= 4) {
                    $notReusableFunc('different DSN');
                }
            } elseif (self::$ciLastConnectPdo !== null) {
                try {
                    self::$ciLastConnectPdo->query('select 1 from dual')->fetch();
                } catch (\PDOException $e) {
                    $notReusableFunc((string) $e);
                }
            }

            if (self::$ciLastConnectPdo !== null && self::$ciLastConnectPdo->inTransaction()) {
                $notReusableFunc('inside transaction');
            }

            if (self::$ciLastConnectPdo !== null) {
                $dbalConnection = parent::createConnection(['pdo' => self::$ciLastConnectPdo]);
            } else {
                $dbalConnection = parent::createConnection($dsn);
            }

            $dbalConnection->getWrappedConnection()->getWrappedConnection(); // @phpstan-ignore-line

            self::$ciLastConnectDsn = $dsn;
        }

        return $dbalConnection;
    }

    public function query(Model $model = null): Persistence\Query
    {
        return new Oracle\Query($model);
    }

    public function lastInsertId(Model $model = null): string
    {
        if ($sequence = $this->getIdSequenceName($model)) {
            return $this->statement()
                ->mode('seq_currval')
                ->sequence($sequence)
                ->execute()
                ->fetchOne();
        }

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
