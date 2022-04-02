<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence;

use Doctrine\DBAL;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Phlex\Core\Factory;
use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

abstract class Sql extends Persistence
{
    public const OPTION_USE_TABLE_PREFIX = self::class . '@use_table_prefix';

    /**
     * Connection object.
     *
     * @var DBAL\Connection|null
     */
    public $connection;

    /**
     * Default class when adding new field.
     *
     * @var array
     */
    public $_default_seed_addField = [Sql\Field::class];

    /**
     * Default class when adding hasOne field.
     *
     * @var array
     */
    public $_default_seed_hasOne = [Sql\Field\Reference\HasOne::class];

    /**
     * Default class when adding withMany field.
     *
     * @var array
     */
    public $_default_seed_withMany = [Sql\Field\Reference\WithMany::class];

    /**
     * Default class when adding Expression field.
     *
     * @var array
     */
    public $_default_seed_addExpression = [Sql\Field\Expression::class];

    /**
     * Default class when adding join.
     *
     * @var array
     */
    public $_default_seed_join = [Sql\Join::class];

    /**
     * Default class when creating statement.
     *
     * @var array
     */
    public $_default_seed_statement = [Sql\Statement::class];

    /**
     * Default class when creating migration.
     *
     * @var array
     */
    public $_default_seed_migration = [Sql\Migration::class];

    protected static $defaultCodecs = [
        [Sql\Codec\String_::class],
        Model\Field\Type\Selectable::class => [Sql\Codec\Selectable::class],
        Model\Field\Type\Array_::class => [Sql\Codec\Array_::class],
        Model\Field\Type\Boolean::class => [Sql\Codec\Boolean::class],
        Model\Field\Type\Date::class => [Sql\Codec\Date::class],
        Model\Field\Type\DateTime::class => [Sql\Codec\DateTime::class],
        Model\Field\Type\Time::class => [Sql\Codec\Time::class],
        Model\Field\Type\Float_::class => [Sql\Codec\Float_::class],
        Model\Field\Type\Integer::class => [Sql\Codec\Integer::class],
        Model\Field\Type\String_::class => [Sql\Codec\String_::class],
        Model\Field\Type\Text::class => [Sql\Codec\Text::class],
        Model\Field\Type\Object_::class => [Sql\Codec\Object_::class],
    ];

    protected static $registry = [
        'mysql' => [Sql\Platform\Mysql::class],
        'oci' => [Sql\Platform\Oracle::class],
        'oci12' => [Sql\Platform\Oracle::class],
        'sqlite' => [Sql\Platform\Sqlite::class],
        'pgsql' => [Sql\Platform\Postgresql::class],
        'sqlsrv' => [Sql\Platform\Mssql::class],
    ];

    /**
     * Connects database.
     *
     * @param string|array $dsn Format as PDO DSN or use "mysql://user:pass@host/db;option=blah",
     *                          leaving user and password arguments = null
     */
    public static function connect($dsn, string $user = null, string $password = null, array $options = []): self
    {
        // parse DSN string
        $dsn = self::normalizeDsn($dsn, $user, $password, $options);

        switch ($dsn['driverSchema']) {
            case 'mysql':
            case 'oci':
            case 'oci12':
                // Omitting UTF8 is always a bad problem, so unless it's specified we will do that
                // to prevent nasty problems. This is un-tested on other databases, so moving it here.
                // It gives problem with sqlite
                if (strpos($dsn['dsn'], ';charset=') === false) {
                    $dsn['dsn'] .= ';charset=utf8mb4';
                }

                // no break
            case 'pgsql':
            case 'sqlsrv':
            case 'sqlite':
                return Factory::factory(self::resolvePersistenceSeed($dsn['driverSchema']), [$dsn['dsn'], $dsn['user'], $dsn['pass'], $options]);
            default:
                throw (new Exception('Unable to determine persistence driver type from DSN'))
                    ->addMoreInfo('dsn', $dsn['dsn']);
        }
    }

    /**
     * Normalize DSN connection string.
     *
     * Returns normalized DSN as array ['dsn', 'user', 'pass', 'driverSchema', 'rest'].
     *
     * @param array|string $dsn  DSN string
     * @param string       $user Optional username, this takes precedence over dsn string
     * @param string       $pass Optional password, this takes precedence over dsn string
     *
     * @return array
     */
    public static function normalizeDsn($dsn, $user = null, $pass = null, $options = [])
    {
        // Try to dissect DSN into parts
        $parts = is_array($dsn) ? $dsn : parse_url($dsn);

        // If parts are usable, convert DSN format
        if ($parts !== false && isset($parts['host'], $parts['path'])) {
            // DSN is using URL-like format, so we need to convert it
            $dsn = $parts['scheme'] . ':host=' . $parts['host']
            . (isset($parts['port']) ? ';port=' . $parts['port'] : '')
            . ';dbname=' . substr($parts['path'], 1);
            $user ??= ($parts['user'] ?? null);
            $pass ??= ($parts['pass'] ?? null);
        }

        // If it's still array, then simply use it
        if (is_array($dsn)) {
            return $dsn;
        }

        // If it's string, then find driver
        if (is_string($dsn)) {
            if (strpos($dsn, ':') === false) {
                throw (new Exception('Your DSN format is invalid. Must be in "driverSchema:host;options" format'))
                    ->addMoreInfo('dsn', $dsn);
            }
            [$driverSchema, $rest] = explode(':', $dsn, 2);
            $driverSchema = strtolower($driverSchema);
        } else {
            // currently impossible to be like this, but we don't want ugly exceptions here
            $driverSchema = null;
            $rest = null;
        }

        return array_merge($options, ['dsn' => $dsn, 'user' => $user ?: null, 'pass' => $pass ?: null, 'driverSchema' => $driverSchema, 'rest' => $rest]);
    }

    public static function createFromConnection(DBAL\Connection $connection)
    {
        $driverSchema = $connection->getWrappedConnection()->getWrappedConnection()->getAttribute(\PDO::ATTR_DRIVER_NAME); // @phpstan-ignore-line

        return Factory::factory($driverSchema, [$connection]);
    }

    /**
     * Adds persistence seed to the registry for resolving in Persistence\Sql::resolvePersistenceSeed method.
     *
     * Can be used as:
     *
     * Persistence\Sql::registerPersistenceSeed('mysql', [Custom\Persistence::class]), or
     * Custom\Persistence::registerPersistenceSeed('mysql')
     *
     * Custom\Persistence must be descendant of Persistence\Sql class.
     */
    public static function registerPersistenceSeed(string $driverSchema, array $persistenceSeed = null)
    {
        self::$registry[$driverSchema] = $persistenceSeed ?? [static::class];
    }

    /**
     * Resolves the persistence seed to use based on the driver type.
     */
    public static function resolvePersistenceSeed(string $driverSchema)
    {
        return self::$registry[$driverSchema] ?? self::$registry[0];
    }

    /**
     * Disconnect from database explicitly.
     */
    public function disconnect(): void
    {
        $this->connection = null;
    }

    /**
     * @param DBAL\Connection|string $connection
     * @param string                 $user
     * @param string                 $password
     * @param array                  $options
     */
    public function __construct($connection, $user = null, $password = null, $options = [])
    {
        if ($connection instanceof DBAL\Connection) {
            $this->connection = $connection;

            return;
        }

        if (is_object($connection)) {
            throw (new Exception('You can only use Persistance_SQL with Connection class from Doctrine\DBAL\Connection'))
                ->addMoreInfo('connection', $connection);
        }

        // attempt to connect.
        $this->connection = self::createConnection(
            static::normalizeDsn($connection, $user, $password, $options)
        );
    }

    /**
     * Establishes connection based on a $dsn.
     */
    protected function createConnection(array $dsn): DBAL\Connection
    {
        if (isset($dsn['pdo'])) {
            $pdo = $dsn['pdo'];
        } else {
            $pdo = new \PDO($dsn['dsn'], $dsn['user'], $dsn['pass']);
        }

        // Doctrine DBAL 3.x does not support to create DBAL Connection with already
        // instanced PDO, so create it without PDO first, see:
        // https://github.com/doctrine/dbal/blob/v2.10.1/lib/Doctrine/DBAL/DriverManager.php#L179
        // https://github.com/doctrine/dbal/blob/3.0.0/src/DriverManager.php#L142
        // TODO probably drop support later
        $pdoConnection = (new \ReflectionClass(DBAL\Driver\PDO\Connection::class))
            ->newInstanceWithoutConstructor();

        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        \Closure::bind(function () use ($pdoConnection, $pdo): void {
            $pdoConnection->connection = $pdo;
        }, null, DBAL\Driver\PDO\Connection::class)();

        $dbalConnection = DBAL\DriverManager::getConnection([
            'driver' => 'pdo_' . $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME),
        ]);

        \Closure::bind(function () use ($dbalConnection, $pdoConnection): void {
            $dbalConnection->_conn = $pdoConnection;
        }, null, DBAL\Connection::class)();

        return $dbalConnection;
    }

    public function getConnection(): DBAL\Connection
    {
        return $this->connection;
    }

    public function statement($defaults = []): Sql\Statement
    {
        return Factory::factory($this->_default_seed_statement, array_merge(
            $defaults,
            [
                'persistence' => $this,
            ],
            $this->connection ? [
                'identifierQuoteCharacter' => $this->connection->getDatabasePlatform()->getIdentifierQuoteCharacter(),
            ] : []
        ));
    }

    public function add(Model $model, array $defaults = []): Model
    {
        // Use our own classes for fields, references and expressions unless
        // $defaults specify them otherwise.
        $defaults = array_merge([
            '_default_seed_addField' => $this->_default_seed_addField,
            '_default_seed_hasOne' => $this->_default_seed_hasOne,
            '_default_seed_withMany' => $this->_default_seed_withMany,
            '_default_seed_addExpression' => $this->_default_seed_addExpression,
            '_default_seed_join' => $this->_default_seed_join,
        ], $defaults);

        $model = parent::add($model, $defaults);

        if (!isset($model->table) || (!is_string($model->table) && !is_array($model->table) && $model->table !== false)) {
            throw (new Exception('Property $table must be specified for a model'))
                ->addMoreInfo('model', $model);
        }

        // When we work without table, we can't have any IDs
        if ($model->table === false) {
            $model->removeField($model->primaryKey);
            $model->addExpression($model->primaryKey, '1');
            // } else {
            // SQL databases use ID of int by default
            // $m->getField($m->primaryKey)->type = 'integer';
        }

        // Sequence support
        if ($model->sequence && $model->hasPrimaryKeyField()) {
            $model->getPrimaryKeyField()->default = $this->statement()->mode('seq_nextval')->sequence($model->sequence);
        }

        return $model;
    }

    protected function configure(Model $model): void
    {
        $model->unsetOption(self::OPTION_USE_TABLE_PREFIX);

        $model->addMethod('migrate', static function (Model $m, ...$args) {
            return $m->persistence->migrate($m, ...$args); // @phpstan-ignore-line
        });
        $model->addMethod('expr', static function (Model $m, $expr, $args = []) {
            preg_replace_callback(
                '/\[[a-z0-9_]*\]|{[a-z0-9_]*}/i',
                function ($matches) use (&$args, $m) {
                    $identifier = substr($matches[0], 1, -1);
                    if ($identifier && !isset($args[$identifier])) {
                        $args[$identifier] = $m->getField($identifier);
                    }

                    return $matches[0];
                },
                $expr
            );

            return $m->persistence->expr($expr, $args); // @phpstan-ignore-line
        });
    }

    /**
     * Execute Expression by using this persistence.
     *
     * @param Sql\Expressionable|string $expressionable
     */
    public function execute($expressionable): DBAL\Result
    {
        if ($this->connection === null) {
            throw new Exception('Queries cannot be executed through this persistence');
        }

        if (is_string($expressionable)) {
            $expressionable = new Sql\Expression($expressionable);
        }

        $expression = $expressionable->toSqlExpression()->consumedInParentheses(false);

        $query = $this->statement();

        $sql = $query->consume($expression);

        try {
            $statement = $this->connection->prepare($sql);

            foreach ($query->params as $key => $value) {
                if (is_int($value)) {
                    $type = \PDO::PARAM_INT;
                } elseif (is_bool($value)) {
                    // SQL does not like booleans at all, so convert them INT
                    if ($this->connection->getDatabasePlatform() instanceof PostgreSQL94Platform) {
                        $type = \PDO::PARAM_STR;
                        $value = $value ? '1' : '0';
                    } else {
                        $type = \PDO::PARAM_INT;
                        $value = $value ? 1 : 0;
                    }
                } elseif ($value === null) {
                    $type = \PDO::PARAM_NULL;
                } elseif (is_string($value) || is_float($value)) {
                    $type = \PDO::PARAM_STR;
                } elseif (is_resource($value)) {
                    throw new Exception('Resource type is not supported, set value as string instead');
                } else {
                    throw (new Exception('Incorrect param type'))
                        ->addMoreInfo('key', $key)
                        ->addMoreInfo('value', $value)
                        ->addMoreInfo('type', gettype($value));
                }

                if ($statement->bindValue($key, $value, $type) === false) {
                    throw (new Exception('Unable to bind parameter'))
                        ->addMoreInfo('param', $key)
                        ->addMoreInfo('value', $value)
                        ->addMoreInfo('type', $type);
                }
            }

            return $statement->execute();
        } catch (DBAL\Exception $e) {
            $firstException = $e;
            while ($firstException->getPrevious() !== null) {
                $firstException = $firstException->getPrevious();
            }
            $errorInfo = $firstException instanceof \PDOException ? $firstException->errorInfo : null;

            throw (new Sql\ExecuteException('Query execute error ' . ($errorInfo[2] ?? 'n/a (' . $errorInfo[0] . ')') . ' ' . $expression->getDebugQuery(), $errorInfo[1] ?? 0, $e))
                ->addMoreInfo('error', $errorInfo[2] ?? 'n/a (' . $errorInfo[0] . ')')
                ->addMoreInfo('query', $expression->getDebugQuery());
        }
    }

    /**
     * Atomic executes operations within one begin/end transaction, so if
     * the code inside callback will fail, then all of the transaction
     * will be also rolled back.
     */
    public function atomic(\Closure $fx, ...$args)
    {
        $this->beginTransaction();
        try {
            $res = $fx(...$args);
            $this->commit();

            return $res;
        } catch (\Exception $e) {
            $this->rollback();

            throw $e;
        }
    }

    /**
     * Starts new transaction.
     *
     * Database driver supports statements for starting and committing
     * transactions. Unfortunately most of them don't allow to nest
     * transactions and commit gradually.
     * With this method you have some implementation of nested transactions.
     *
     * When you call it for the first time it will begin transaction. If you
     * call it more times, it will do nothing but will increase depth counter.
     * You will need to call commit() for each execution of beginTransactions()
     * and only the last commit will perform actual commit in database.
     *
     * So, if you have been working with the database and got un-handled
     * exception in the middle of your code, everything will be rolled back.
     */
    public function beginTransaction(): void
    {
        try {
            $this->connection->beginTransaction();
        } catch (DBAL\ConnectionException $e) {
            throw new Exception('Begin transaction failed', 0, $e);
        }
    }

    /**
     * Will return true if currently running inside a transaction.
     * This is useful if you are logging anything into a database. If you are
     * inside a transaction, don't log or it may be rolled back.
     * Perhaps use a hook for this?
     */
    public function inTransaction(): bool
    {
        return $this->connection->isTransactionActive();
    }

    /**
     * Commits transaction.
     *
     * Each occurrence of beginTransaction() must be matched with commit().
     * Only when same amount of commits are executed, the actual commit will be
     * issued to the database.
     */
    public function commit(): void
    {
        try {
            $this->connection->commit();
        } catch (DBAL\ConnectionException $e) {
            throw new Exception('Commit failed', 0, $e);
        }
    }

    /**
     * Rollbacks queries since beginTransaction and resets transaction depth.
     */
    public function rollback(): void
    {
        try {
            $this->connection->rollBack();
        } catch (DBAL\ConnectionException $e) {
            throw new Exception('Rollback failed', 0, $e);
        }
    }

    public function migrate(Model $model): Sql\Migration
    {
        return $this->createMigrator($model)->create();
    }

    public function createMigrator(Model $model = null): Sql\Migration
    {
        return Factory::factory(Factory::mergeSeeds($this->_default_seed_migration, ['source' => $model ?: $this->connection]));
    }

    /**
     * Creates new Expression object from expression string.
     */
    public function expr($expr, array $args = []): Sql\Expression
    {
        $expression = new Sql\Expression($expr, $args);

        $expression->persistence = $this;

        return $expression;
    }

    /**
     * Creates new Query object with current_timestamp(precision) expression.
     */
    public function exprNow(int $precision = null): Sql\Expression
    {
        return $this->statement()->exprNow($precision);
    }

    public function query(Model $model = null): Persistence\Query
    {
        return new Sql\Query($model);
    }

    protected function getIdSequenceName(Model $model): ?string
    {
        return $model->sequence ?: null;
    }

    public function lastInsertId(Model $model = null): string
    {
        return $this->connection->lastInsertId($this->getIdSequenceName($model));
    }
}
