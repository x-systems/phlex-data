<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence;

use Atk4\Dsql\Connection;
use Atk4\Dsql\Expression;
use Atk4\Dsql\Query;
use Phlex\Core\Factory;
use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

abstract class Sql extends Persistence
{
    /** @const string */
    public const HOOK_INIT_SELECT_QUERY = self::class . '@initSelectQuery';
    /** @const string */
    public const HOOK_BEFORE_INSERT_QUERY = self::class . '@beforeInsertQuery';
    /** @const string */
    public const HOOK_AFTER_INSERT_QUERY = self::class . '@afterInsertQuery';
    /** @const string */
    public const HOOK_BEFORE_UPDATE_QUERY = self::class . '@beforeUpdateQuery';
    /** @const string */
    public const HOOK_AFTER_UPDATE_QUERY = self::class . '@afterUpdateQuery';
    /** @const string */
    public const HOOK_BEFORE_DELETE_QUERY = self::class . '@beforeDeleteQuery';

    /**
     * Connection object.
     *
     * @var \Atk4\Dsql\Connection
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
    public $_default_seed_hasOne = [Sql\Reference\HasOne::class];

    /**
     * Default class when adding hasMany field.
     *
     * @var array
     */
    public $_default_seed_hasMany; // [Sql\Reference\HasMany::class];

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
     * Default class when creating migration.
     *
     * @var array
     */
    public $_default_seed_migration = [Sql\Migration::class];

    protected static $defaultCodecs = [
        [Sql\Codec\String_::class],
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
        [Sql\Platform\Generic::class],
        'oci' => [Sql\Platform\Oracle::class],
        'oci12' => [Sql\Platform\Oracle::class],
        'sqlite' => [Sql\Platform\Sqlite::class],
        'pgsql' => [Sql\Platform\Postgresql::class],
    ];

    /**
     * Connects database.
     *
     * @param string|array $dsn Format as PDO DSN or use "mysql://user:pass@host/db;option=blah",
     *                          leaving user and password arguments = null
     */
    public static function connect($dsn, string $user = null, string $password = null, array $args = []): self
    {
        // parse DSN string
        $dsn = \Atk4\Dsql\Connection::normalizeDsn($dsn, $user, $password);

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
                return Factory::factory(self::resolve($dsn['driverSchema']), [$dsn['dsn'], $dsn['user'], $dsn['pass'], $args]);
            default:
                throw (new Exception('Unable to determine persistence driver type from DSN'))
                    ->addMoreInfo('dsn', $dsn['dsn']);
        }
    }

    public static function createFromConnection(Connection $connection)
    {
        $driverSchema = $connection->connection()->getWrappedConnection()->getWrappedConnection()->getAttribute(\PDO::ATTR_DRIVER_NAME); // @phpstan-ignore-line

        return Factory::factory($driverSchema, [$connection]);
    }

    public static function resolve($driverSchema)
    {
        return self::$registry[$driverSchema] ?? self::$registry[0];
    }

    /**
     * Disconnect from database explicitly.
     */
    public function disconnect(): void
    {
        $this->connection = null; // @phpstan-ignore-line
    }

    /**
     * Constructor.
     *
     * @param Connection|string $connection
     * @param string            $user
     * @param string            $password
     * @param array             $args
     */
    public function __construct($connection, $user = null, $password = null, $args = [])
    {
        if ($connection instanceof \Atk4\Dsql\Connection) {
            $this->connection = $connection;

            return;
        }

        if (is_object($connection)) {
            throw (new Exception('You can only use Persistance_SQL with Connection class from Atk4\Dsql'))
                ->addMoreInfo('connection', $connection);
        }

        // attempt to connect.
        $this->connection = \Atk4\Dsql\Connection::connect(
            $connection,
            $user,
            $password,
            $args
        );
    }

    /**
     * Returns Query instance.
     */
    public function dsql(): Query
    {
        return $this->connection->dsql();
    }

    /**
     * Atomic executes operations within one begin/end transaction, so if
     * the code inside callback will fail, then all of the transaction
     * will be also rolled back.
     *
     * @return mixed
     */
    public function atomic(\Closure $fx)
    {
        return $this->connection->atomic($fx);
    }

    /**
     * {@inheritdoc}
     */
    public function add(Model $model, array $defaults = []): Model
    {
        // Use our own classes for fields, references and expressions unless
        // $defaults specify them otherwise.
        $defaults = array_merge([
            '_default_seed_addField' => $this->_default_seed_addField,
            '_default_seed_hasOne' => $this->_default_seed_hasOne,
            '_default_seed_hasMany' => $this->_default_seed_hasMany,
            '_default_seed_addExpression' => $this->_default_seed_addExpression,
            '_default_seed_join' => $this->_default_seed_join,
        ], $defaults);

        $model = parent::add($model, $defaults);

        if (!isset($model->table) || (!is_string($model->table) && $model->table !== false)) {
            throw (new Exception('Property $table must be specified for a model'))
                ->addMoreInfo('model', $model);
        }

        // When we work without table, we can't have any IDs
        if ($model->table === false) {
            $model->removeField($model->primaryKey);
            $model->addExpression($model->primaryKey, '1');
            //} else {
            // SQL databases use ID of int by default
            //$m->getField($m->primaryKey)->type = 'integer';
        }

        // Sequence support
        if ($model->sequence && $model->hasPrimaryKeyField()) {
            $model->getPrimaryKeyField()->default = $this->dsql()->mode('seq_nextval')->sequence($model->sequence);
        }

        return $model;
    }

    /**
     * Initialize persistence.
     */
    protected function initPersistence(Model $model): void
    {
        $model->addMethod('migrate', static function (Model $m, ...$args) {
            return $m->persistence->migrate($m, ...$args); // @phpstan-ignore-line
        });
        $model->addMethod('expr', static function (Model $m, ...$args) {
            return $m->persistence->expr($m, ...$args); // @phpstan-ignore-line
        });
        $model->addMethod('dsql', static function (Model $m, ...$args) {
            return $m->persistence->dsql($m, ...$args); // @phpstan-ignore-line
        });
        $model->addMethod('exprNow', static function (Model $m, ...$args) {
            return $m->persistence->exprNow($m, ...$args); // @phpstan-ignore-line
        });
    }

    public function migrate(Model $model): Sql\Migration
    {
        return $this->getMigration($model)->create();
    }

    public function getMigration(Model $model = null): Sql\Migration
    {
        return Factory::factory(Factory::mergeSeeds($this->_default_seed_migration, ['source' => $model ?: $this->connection]));
    }

    /**
     * Creates new Expression object from expression string.
     *
     * @param mixed $expr
     */
    public function expr(Model $model, $expr, array $args = []): Expression
    {
        if (!is_string($expr)) {
            return $this->connection->expr($expr, $args);
        }
        preg_replace_callback(
            '/\[[a-z0-9_]*\]|{[a-z0-9_]*}/i',
            function ($matches) use (&$args, $model) {
                $identifier = substr($matches[0], 1, -1);
                if ($identifier && !isset($args[$identifier])) {
                    $args[$identifier] = $model->getField($identifier);
                }

                return $matches[0];
            },
            $expr
        );

        return $this->connection->expr($expr, $args);
    }

    /**
     * Creates new Query object with current_timestamp(precision) expression.
     */
    public function exprNow(int $precision = null): Expression
    {
        return $this->connection->dsql()->exprNow($precision);
    }

    public function query(Model $model): Persistence\Query
    {
        return new Sql\Query($model, $this);
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
