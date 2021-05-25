<?php

declare(strict_types=1);

namespace Phlex\Data\Model;

use Phlex\Core\ContainerTrait;
use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence\Sql;

/**
 * @property Scope\AbstractScope[] $elements
 */
class Scope extends Scope\AbstractScope
{
    use ContainerTrait;

    // junction definitions
    public const JUNCTION_OR = 'OR';
    public const JUNCTION_AND = 'AND';

    /** @var self::JUNCTION_AND|self::JUNCTION_OR Junction to use in case more than one element. */
    protected $junction = self::JUNCTION_AND;

    /**
     * Create a Scope from array of condition objects or condition arrays.
     *
     * @param array<int, Scope\AbstractScope|string|Expressionable|array<mixed>> $nestedConditions
     */
    public function __construct(array $nestedConditions = [], string $junction = self::JUNCTION_AND)
    {
        if (!in_array($junction, [self::JUNCTION_OR, self::JUNCTION_AND], true)) {
            throw (new Exception('Using invalid Scope junction'))
                ->addMoreInfo('junction', $junction);
        }

        $this->junction = $junction;

        foreach ($nestedConditions as $nestedCondition) {
            if ($nestedCondition instanceof Scope\AbstractScope) {
                $condition = $nestedCondition;
            } else {
                if (!is_array($nestedCondition)) {
                    $nestedCondition = [$nestedCondition];
                }
                $condition = new Scope\Condition(...$nestedCondition);
            }

            $this->add($condition);
        }
    }

    public function __clone()
    {
        foreach ($this->elements as $k => $nestedCondition) {
            $this->elements[$k] = clone $nestedCondition;
            if ($this->elements[$k]->issetOwner()) {
                $this->elements[$k]->unsetOwner();
            }
            $this->elements[$k]->setOwner($this);
            $this->elements[$k]->short_name = $nestedCondition->short_name;
        }
        if ($this->issetOwner()) {
            $this->unsetOwner();
        }
        $this->short_name = null; // @phpstan-ignore-line
    }

    /**
     * @param Scope\AbstractScope|array|string|Sql\Expressionable $field
     * @param string|mixed|null                                   $operator
     * @param mixed|null                                          $value
     *
     * @return $this
     */
    public function addCondition($field, $operator = null, $value = null)
    {
        if (func_num_args() === 1 && $field instanceof Scope\AbstractScope) {
            $condition = $field;
        } elseif (func_num_args() === 1 && is_array($field)) {
            $condition = static::createAnd(func_get_args());
        } else {
            $condition = new Scope\Condition(...func_get_args());
        }

        $this->add($condition);

        return $this;
    }

    /**
     * Return array of nested conditions.
     *
     * @return Scope\AbstractScope[]
     */
    public function getNestedConditions()
    {
        return $this->elements;
    }

    protected function onChangeModel(): void
    {
        foreach ($this->elements as $nestedCondition) {
            $nestedCondition->onChangeModel();
        }
    }

    public function isEmpty(): bool
    {
        return count($this->elements) === 0;
    }

    public function isCompound(): bool
    {
        return count($this->elements) > 1;
    }

    /**
     * @return self::JUNCTION_AND|self::JUNCTION_OR
     */
    public function getJunction(): string
    {
        return $this->junction;
    }

    /**
     * Checks if junction is JUNCTION_OR.
     */
    public function isOr(): bool
    {
        return $this->junction === self::JUNCTION_OR;
    }

    /**
     * Checks if junction is JUNCTION_AND.
     */
    public function isAnd(): bool
    {
        return $this->junction === self::JUNCTION_AND;
    }

    /**
     * Clears the compound condition from nested conditions.
     *
     * @return static
     */
    public function clear()
    {
        $this->elements = [];

        return $this;
    }

    public function simplify(): Scope\AbstractScope
    {
        if (count($this->elements) !== 1) {
            return $this;
        }

        /** @var Scope\AbstractScope $component */
        $component = reset($this->elements);

        return $component->simplify();
    }

    /**
     * Use De Morgan's laws to negate.
     *
     * @return static
     */
    public function negate()
    {
        $this->junction = $this->junction === self::JUNCTION_OR ? self::JUNCTION_AND : self::JUNCTION_OR;

        foreach ($this->elements as $nestedCondition) {
            $nestedCondition->negate();
        }

        return $this;
    }

    public function toWords(Model $model = null): string
    {
        $parts = [];
        foreach ($this->elements as $nestedCondition) {
            $words = $nestedCondition->toWords($model);

            $parts[] = $this->isCompound() && $nestedCondition->isCompound() ? '(' . $words . ')' : $words;
        }

        $glue = ' ' . strtolower($this->junction) . ' ';

        return implode($glue, $parts);
    }

    /**
     * @param Scope\AbstractScope|string|Sql\Expressionable|array<mixed> ...$conditions
     *
     * @return static
     */
    public static function createAnd(...$conditions)
    {
        return new static($conditions, self::JUNCTION_AND);
    }

    /**
     * @param Scope\AbstractScope|string|Sql\Expressionable|array<mixed> ...$conditions
     *
     * @return static
     */
    public static function createOr(...$conditions)
    {
        return new static($conditions, self::JUNCTION_OR);
    }
}
