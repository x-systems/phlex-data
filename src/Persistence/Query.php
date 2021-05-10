<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence;

use Doctrine\DBAL\Result;
use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

abstract class Query implements \IteratorAggregate
{
    /** @const string */
    public const HOOK_INIT_SELECT = self::class . '@initSelect';
    /** @const string */
    public const HOOK_BEFORE_INSERT = self::class . '@beforeInsert';
    /** @const string */
    public const HOOK_AFTER_INSERT = self::class . '@afterInsert';
    /** @const string */
    public const HOOK_BEFORE_UPDATE = self::class . '@beforeUpdate';
    /** @const string */
    public const HOOK_AFTER_UPDATE = self::class . '@afterUpdate';
    /** @const string */
    public const HOOK_BEFORE_DELETE = self::class . '@beforeDelete';

    public const MODE_SELECT = 'select';
    public const MODE_UPDATE = 'update';
    public const MODE_INSERT = 'insert';
    public const MODE_DELETE = 'delete';

    /** @var Model|null */
    protected $model;

    /** @var Model\Scope */
    protected $scope;

    /** @var array */
    protected $order = [];

    /** @var array */
    protected $limit = [];

    /** @var string */
    protected $mode;

    public function __construct(Model $model)
    {
        $this->model = $model;

        $this->scope = clone $this->model->scope();

        $this->order = $model->order;

        $this->limit = $model->limit;
    }

    public function getPersistence(): ?Persistence
    {
        return $this->model->persistence;
    }

    public function find($id): ?array
    {
        return $this->whereId($id)->getRow();
    }

    /**
     * Setup query as selecting list of $fields or all if $field = NULL.
     *
     * @param array|false|null $fields
     */
    public function select($fields = null): self
    {
        $this->initWhere();
        $this->initLimit();
        $this->initOrder();
        $this->initSelect($fields);

        $this->hookInitSelect(__FUNCTION__);

        return $this;
    }

    abstract protected function initSelect($fields = null): void;

    public function update(array $data): self
    {
        $this->initWhere();
        $this->initUpdate($data);

        $this->setMode(self::MODE_UPDATE);

        return $this;
    }

    abstract protected function initUpdate(array $data): void;

    public function insert(array $data): self
    {
        $this->initInsert($data);

        $this->setMode(self::MODE_INSERT);

        return $this;
    }

    abstract protected function initInsert(array $data): void;

    /**
     * Setup query as deleting record(s) within the Query::$scope.
     * If $id argument provided only record with $id will be deleted if within the scope.
     */
    public function delete(): self
    {
        $this->initWhere();
        $this->initLimit();
        $this->initOrder();
        $this->initDelete();

        $this->setMode(self::MODE_DELETE);

        $this->hookInitSelect(__FUNCTION__);

        return $this;
    }

    abstract protected function initDelete(): void;

    public function exists(): self
    {
        $this->initWhere();
        $this->initExists();

        $this->hookInitSelect(__FUNCTION__);

        return $this;
    }

    abstract protected function initExists(): void;

    public function count($alias = null): self
    {
        $this->initWhere();
        $this->initCount(...func_get_args());

        $this->hookInitSelect(__FUNCTION__);

        return $this;
    }

    abstract protected function initCount($alias = null): void;

    public function aggregate(string $functionName, $field, string $alias = null, bool $coalesce = false): self
    {
        $this->initWhere();
        $this->initLimit();
        $this->initOrder();
        $this->initAggregate($functionName, $field, $alias, $coalesce);

        $this->hookInitSelect(__FUNCTION__);

        return $this;
    }

    abstract protected function initAggregate(string $functionName, $field, string $alias = null, bool $coalesce = false): void;

    public function field($fieldName, string $alias = null): self
    {
        $this->initWhere();
        $this->initLimit();
        $this->initOrder();
        $this->initField(...func_get_args());

        if ($this->model && $this->model->loaded()) {
            $this->whereId($this->model->getId());
        }

        $this->hookInitSelect(__FUNCTION__);

        return $this;
    }

    abstract protected function initField($fieldName, string $alias = null): void;

    protected function withMode(): self
    {
        if (!$this->mode) {
            $this->select();
        }

        return $this;
    }

    protected function hookInitSelect($type): void
    {
        if ($this->mode !== self::MODE_SELECT) {
            if (!$this->mode) {
                $this->setMode(self::MODE_SELECT);
            }

            $this->hookOnModel('init', [$this, $type]);
        }
    }

    protected function setMode($mode): self
    {
        if ($this->mode) {
            throw (new Exception('Mode of query cannot be modified'))
                ->addMoreInfo('existing_mode', $this->mode)
                ->addMoreInfo('attempted_mode', $mode);
        }

        $this->mode = $mode;

        return $this;
    }

    /**
     * Add condition to the query scope (leaves model scope intact).
     */
    public function where($fieldName, $operator = null, $value = null): self
    {
        $this->scope->addCondition(...func_get_args());

        return $this;
    }

    public function whereId($id): self
    {
        if (!$this->model || !$this->model->primaryKey) {
            throw (new Exception('Unable to find record by "id" when Model::primaryKey is not defined.'))
                ->addMoreInfo('id', $id);
        }

        $primaryKeyField = $this->model->getPrimaryKeyField();

        return $this->where($primaryKeyField, $primaryKeyField->normalize($id));
    }

    abstract protected function initWhere(): void;

    public function order($field, $desc = null): self
    {
        $this->order[] = [$field, $desc];

        $this->initOrder();

        return $this;
    }

    abstract protected function initOrder(): void;

    public function limit($limit, $offset = 0): self
    {
        $this->limit = [$limit, $offset];

        $this->initLimit();

        return $this;
    }

    abstract protected function initLimit(): void;

    /**
     * Converts limit array to arguments [$limit, $offset].
     */
    protected function getLimitArgs()
    {
        if ($this->limit) {
            $offset = $this->limit[1] ?? 0;
            $limit = $this->limit[0] ?? null;

            if ($limit || $offset) {
                if ($limit === null) {
                    $limit = PHP_INT_MAX;
                }

                return [$limit, $offset ?? 0];
            }
        }
    }

    public function execute(): Result
    {
        return $this->executeQueryWithDebug(function () {
            $this->withMode();

            $this->hookOnModel('before', [$this]);

            $result = $this->doExecute();

            $this->hookOnModel('after', [$this, $result]);

            return $result;
        });
    }

    abstract protected function doExecute(): Result;

    /**
     * Creates hooks based on $stage and self::$mode
     * HOOK_BEFORE_SELECT_QUERY
     * HOOK_AFTER_SELECT_QUERY
     * HOOK_BEFORE_UPDATE_QUERY
     * HOOK_AFTER_UPDATE_QUERY
     * HOOK_BEFORE_INSERT_QUERY
     * HOOK_AFTER_INSERT_QUERY
     * HOOK_BEFORE_DELETE_QUERY
     * HOOK_AFTER_DELETE_QUERY.
     */
    protected function hookOnModel(string $stage, array $args = []): void
    {
        $hookSpotConst = static::class . '::' . strtoupper('HOOK_' . $stage . '_' . $this->getMode());
        if (defined($hookSpotConst)) {
            $this->model->hook(constant($hookSpotConst), $args);
        }
    }

    /**
     * Get array of records matching the query.
     */
    public function getRows(): array
    {
        return $this->executeQueryWithDebug(function () {
            return $this->withMode()->doGetRows();
        });
    }

    abstract protected function doGetRows(): array;

    /**
     * Get one row from the records matching the query.
     */
    public function getRow(): ?array
    {
        return $this->executeQueryWithDebug(function () {
            $this->withMode()->limit(1);

            return $this->doGetRow();
        });
    }

    abstract protected function doGetRow(): ?array;

    /**
     * Get value from the first field of the first record in the query results.
     */
    public function getOne()
    {
        return $this->executeQueryWithDebug(function () {
            return $this->doGetOne();
        });
    }

    abstract protected function doGetOne();

    protected function executeQueryWithDebug(\Closure $fx)
    {
        try {
            return $fx();
        } catch (Exception $e) {
            throw (new Exception('Execution of query failed: ' . $e->getMessage() . ': ' . print_r($this->getDebug(), true), 0, $e))
                ->addMoreInfo('message', $e->getMessage())
                ->addMoreInfo('query', $this->getDebug());
        }
    }

    public function getIterator(): \Traversable
    {
        return $this->execute()->iterateAssociative();
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Return the mode the query debug array with necessary details.
     */
    public function getDebug(): array
    {
        return [
            'mode' => $this->mode,
            'model' => $this->model,
            'scope' => $this->scope->toWords($this->model),
            'order' => $this->order,
            'limit' => $this->limit,
        ];
    }
}
