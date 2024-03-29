<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Iterator;

use Doctrine\DBAL\Result;
use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Model\Scope\Condition;
use Phlex\Data\Persistence;

/**
 * Class to perform queries on Array_ persistence.
 *
 * @method Persistence\Array_ getPersistence()
 */
class Query extends Persistence\Query
{
    /** @var \Iterator */
    protected $iterator;

    protected $fields;

    /**
     * Closure to be executed when calling Query::execute.
     *
     * @var \Closure
     */
    protected $fx;

    public function __construct(Model $model)
    {
        parent::__construct($model);

        $this->iterator = $this->getPersistence()->getRawDataIterator($model);

        $this->fx = fn (\Iterator $iterator) => new Query\Result($iterator);
    }

    protected function initSelect($fields = null): void
    {
        if ($fields) {
            $this->fields = $fields;

            $keys = array_flip((array) $this->fields);

            $this->fx = function (\Iterator $iterator) use ($keys) {
                return new Query\Result(function () use ($iterator, $keys) {
                    foreach ($iterator as $id => $row) {
                        yield $id => array_intersect_key($row, $keys);
                    }
                });
            };
        }
    }

    protected function initInsert(array $data): void
    {
        $this->fx = function (\Iterator $iterator) use ($data) {
            $this->getPersistence()->setRawData($this->model, $data, $data[$this->model->primaryKey] ?? null);

            return new Query\Result(null, 1);
        };
    }

    protected function initUpdate(array $data): void
    {
        $this->fx = function (\Iterator $iterator) use ($data) {
            $rowsCount = 0;

            foreach ($iterator as $id => $row) {
                $this->getPersistence()->setRawData($this->model, array_merge($row, $data), $data[$this->model->primaryKey] ?? $id);

                ++$rowsCount;
            }

            return new Query\Result(null, $rowsCount);
        };
    }

    protected function initDelete(): void
    {
        $this->fx = function (\Iterator $iterator) {
            $rowsCount = 0;
            foreach ($iterator as $id => $row) {
                $this->getPersistence()->unsetRawData($this->model->table, $id);

                ++$rowsCount;
            }

            return new Query\Result(null, $rowsCount);
        };
    }

    /**
     * Applies sorting on Iterator.
     */
    protected function initOrder(): void
    {
        if ($this->order) {
            $data = $this->doGetRows();

            // prepare arguments for array_multisort()
            $args = [];
            foreach ($this->order as [$field, $order]) {
                $args[] = array_column($data, $field);
                $args[] = $order === 'desc' ? \SORT_DESC : \SORT_ASC;
            }
            $args[] = &$data;

            // call sorting
            array_multisort(...$args);

            // put data back in generator
            $this->iterator = new \ArrayIterator(array_pop($args));
        }
    }

    protected function initLimit(): void
    {
        if ($args = $this->getLimitArgs()) {
            [$count, $offset] = $args;

            $this->iterator = new \LimitIterator($this->cloneIterator(), $offset, $count);
        }
    }

    /**
     * Counts number of rows and replaces our generator with just a single number.
     */
    protected function initCount($alias = null): void
    {
        // @todo: kept for BC, inconstent results with SQL count!
        $this->initLimit();

        $this->iterator = new \ArrayIterator([[$alias ?? 'count' => iterator_count($this->iterator)]]);
    }

    /**
     * Checks if iterator has any rows.
     */
    protected function initExists(): void
    {
        $this->iterator = new \ArrayIterator([[$this->iterator->valid() ? 1 : 0]]);
    }

    protected function initField($key, string $alias = null): void
    {
        if (!$key) {
            throw new Exception('Field query requires field name');
        }

        $rows = [];
        foreach ($this->getPersistence()->query($this->model)->select([$key])->getRows() as $id => $row) {
            $rows[$id] = [$alias ?? $key => $row[$key]];
        }

        $this->iterator = new \ArrayIterator($rows);
    }

    protected function doExecute(): Result
    {
        return ($this->fx)($this->iterator);
    }

    /**
     * Return all data inside array.
     */
    protected function doGetRows(): array
    {
        $rows = $this->doExecute()->fetchAllAssociative() ?: [];

        // use primary key for key in resulting array
        if ($this->model->primaryKey) {
            if ($primaryKeyColumn = array_column($rows, $this->model->primaryKey)) {
                $rows = array_combine($primaryKeyColumn, $rows);
            }
        }

        return $rows;
    }

    /**
     * Return one row of data.
     */
    protected function doGetRow(): ?array
    {
        return $this->doExecute()->fetchAssociative() ?: null;
    }

    /**
     * Return one value from one row of data.
     *
     * @return mixed
     */
    protected function doGetOne()
    {
        return $this->doExecute()->fetchOne();
    }

    /**
     * Calculates SUM|AVG|MIN|MAX aggragate values for $field.
     *
     * @param string|Model\Field $field
     */
    protected function initAggregate(string $functionName, $field, string $alias = null, bool $coalesce = false): void
    {
        $field = is_string($field) ? $field : $field->getKey();

        $result = 0;
        $column = array_column($this->getRows(), $field);

        switch (strtoupper($functionName)) {
            case 'SUM':
                $result = array_sum($column);

                break;
            case 'AVG':
                $column = $coalesce ? $column : array_filter($column, fn ($value) => $value !== null);

                $result = array_sum($column) / count($column);

                break;
            case 'MAX':
                $result = max($column);

                break;
            case 'MIN':
                $result = min($column);

                break;
            default:
                throw (new Exception('Persistence\Array_ query unsupported aggregate function'))
                    ->addMoreInfo('function', $functionName);
        }

        $this->iterator = new \ArrayIterator([[$result]]);
    }

    /**
     * Applies FilterIterator.
     */
    protected function initWhere(): void
    {
        if (!$this->scope->isEmpty()) {
            // CallbackFilterIterator with circular reference (bound function) is not GCed,
            // because of specific php implementation of SPL iterator, see:
            // https://bugs.php.net/bug.php?id=80125
            // and related
            // https://bugs.php.net/bug.php?id=65387
            // - PHP 7.4 - fix it using WeakReference
            // - PHP 8.0 - fixed in php, see:
            // https://github.com/php/php-src/commit/afab9eb48c883766b7870f76f2e2b0a4bd575786
            // remove the if below once PHP 7.4 is no longer supported
            $filterFx = fn ($row) => $this->match($row, $this->scope);
            if (\PHP_MAJOR_VERSION === 7 && \PHP_MINOR_VERSION === 4) {
                $filterFxWeakRef = \WeakReference::create($filterFx);
                $this->iterator = new \CallbackFilterIterator($this->cloneIterator(), static fn (array $row) => $filterFxWeakRef->get()($row));
                $this->iterator->filterFx = $filterFx; // @phpstan-ignore-line - prevent filter function to be GCed
            } else {
                $this->iterator = new \CallbackFilterIterator($this->cloneIterator(), $filterFx);
            }
        }
    }

    /**
     * Checks if $row matches $condition.
     */
    protected function match(array $row, Model\Scope\AbstractScope $condition): bool
    {
        $match = false;

        // simple condition
        if ($condition instanceof Model\Scope\Condition) {
            $args = $condition->toQueryArguments();

            $field = $args[0];
            $operator = $args[1] ?? null;
            $value = $args[2] ?? null;
            if (count($args) === 2) {
                $value = $operator;

                $operator = Condition::OPERATOR_EQUALS;
            }

            if (!is_a($field, Model\Field::class)) {
                throw (new Exception('Persistence\Array_ driver condition unsupported format'))
                    ->addMoreInfo('reason', 'Unsupported object instance ' . get_class($field))
                    ->addMoreInfo('condition', $condition);
            }

            $match = $this->evaluateIf($row[$field->actual ?? $field->elementId] ?? null, $operator, $value);
        }

        // nested conditions
        if ($condition instanceof Model\Scope) {
            $matches = [];

            foreach ($condition->getNestedConditions() as $nestedCondition) {
                $matches[] = $subMatch = (bool) $this->match($row, $nestedCondition);

                // do not check all conditions if any match required
                if ($condition->isOr() && $subMatch) {
                    break;
                }
            }

            // any matches && all matches the same (if all required)
            $match = array_filter($matches) && ($condition->isAnd() ? count(array_unique($matches)) === 1 : true);
        }

        return $match;
    }

    protected function evaluateIf($v1, $operator, $v2): bool
    {
        if ($v2 instanceof self) {
            $v2 = $v2->getRows();
        }

        if ($v2 instanceof \Traversable) {
            throw new \Exception('Unexpected v2 type');
        }

        switch (strtoupper((string) $operator)) {
            case Condition::OPERATOR_EQUALS:
                $result = is_array($v2) ? $this->evaluateIf($v1, Condition::OPERATOR_IN, $v2) : $v1 === $v2;

                break;
            case Condition::OPERATOR_GREATER:
                $result = $v1 > $v2;

                break;
            case Condition::OPERATOR_GREATER_EQUAL:
                $result = $v1 >= $v2;

                break;
            case Condition::OPERATOR_LESS:
                $result = $v1 < $v2;

                break;
            case Condition::OPERATOR_LESS_EQUAL:
                $result = $v1 <= $v2;

                break;
            case Condition::OPERATOR_DOESNOT_EQUAL:
                $result = !$this->evaluateIf($v1, Condition::OPERATOR_EQUALS, $v2);

                break;
            case Condition::OPERATOR_LIKE:
                $pattern = str_ireplace('%', '(.*?)', preg_quote($v2));

                $result = (bool) preg_match('/^' . $pattern . '$/', (string) $v1);

                break;
            case Condition::OPERATOR_NOT_LIKE:
                $result = !$this->evaluateIf($v1, Condition::OPERATOR_LIKE, $v2);

                break;
            case Condition::OPERATOR_IN:
                $result = false;
                foreach ($v2 as $v2Item) { // TODO flatten rows, this looses column names!
                    if ($this->evaluateIf($v1, '=', $v2Item)) {
                        $result = true;

                        break;
                    }
                }

                break;
            case Condition::OPERATOR_NOT_IN:
                $result = !$this->evaluateIf($v1, Condition::OPERATOR_IN, $v2);

                break;
            case Condition::OPERATOR_REGEXP:
                $result = (bool) preg_match('/' . $v2 . '/', $v1);

                break;
            case Condition::OPERATOR_NOT_REGEXP:
                $result = !$this->evaluateIf($v1, Condition::OPERATOR_REGEXP, $v2);

                break;
            default:
                throw (new Exception('Unsupported operator'))
                    ->addMoreInfo('operator', $operator);
        }

        return $result;
    }

    /**
     * Create completely new iterator object to be used in IteratorIterators.
     */
    protected function cloneIterator(): \Iterator
    {
        return new \ArrayIterator(iterator_to_array($this->iterator));
    }

    public function getDebug(): array
    {
        return array_merge([
            'fields' => $this->fields,
        ], parent::getDebug());
    }
}
