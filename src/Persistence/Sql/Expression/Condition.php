<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Expression;

use Phlex\Data\Exception;
use Phlex\Data\Persistence\Sql;

class Condition extends Sql\Expression
{
    public const JUNCTION_AND = ' and ';
    public const JUNCTION_OR = ' or ';

    protected $template = '[conditions]';

    /** @var string */
    protected $junction = self::JUNCTION_AND;

    /** @var array<array> */
    protected $conditions = [];

    public function __construct(string $junction = self::JUNCTION_AND)
    {
        if (!in_array($junction, [self::JUNCTION_AND, self::JUNCTION_OR], true)) {
            throw (new Exception('Unsupported junction operator'))
                ->addMoreInfo('junction', $junction);
        }

        $this->junction = $junction;
    }

    public function where($field, $operator = null, $value = null)
    {
        $numArgs = func_num_args();

        // Array as first argument means we have to replace it with orExpr()
        if ($numArgs === 1 && is_array($field)) {
            // or conditions
            $or = self::or();
            foreach ($field as $row) {
                if (is_array($row)) {
                    $or->where(...$row);
                } else {
                    $or->where($row);
                }
            }
            $field = $or;
        }

        // first argument is string containing more than just a field name and no more than 2
        // arguments means that we either have a string expression or embedded condition.
        if ($numArgs === 2 && is_string($field) && !preg_match('/^[.a-zA-Z0-9_]*$/', $field)) {
            // field contains non-alphanumeric values. Look for condition
            preg_match(
                '/^([^ <>!=]*)([><!=]*|( *(not|is|in|like))*) *$/',
                $field,
                $matches
            );

            // matches[2] will contain the condition, but $operator will contain the value
            $value = $operator;
            $operator = $matches[2];

            // if we couldn't clearly identify the condition, we might be dealing with
            // a more complex expression. If expression is followed by another argument
            // we need to add equation sign  where('now()',123).
            if (!$operator) {
                $matches[1] = new Sql\Expression($field);

                $operator = '=';
            } else {
                ++$numArgs;
            }

            $field = $matches[1];
        }

        switch ($numArgs) {
            case 1:
                if (is_string($field)) {
                    $field = new Sql\Expression($field);
                }

                $this->conditions[] = [$field->consumedInParentheses()];

                break;
            case 2:
                if (is_object($operator) && !$operator instanceof Sql\Expressionable) {
                    throw (new Exception('Value cannot be converted to SQL-compatible expression'))
                        ->addMoreInfo('field', $field)
                        ->addMoreInfo('value', $operator);
                }

                $this->conditions[] = [$field, $operator];

                break;
            case 3:
                if (is_object($value) && !$value instanceof Sql\Expressionable) {
                    throw (new Exception('Value cannot be converted to SQL-compatible expression'))
                        ->addMoreInfo('field', $field)
                        ->addMoreInfo('cond', $operator)
                        ->addMoreInfo('value', $value);
                }

                $this->conditions[] = [$field, $operator, $value];

                break;
        }

        return $this;
    }

    /**
     * Same syntax as where().
     *
     * @param mixed                 $field    Field, array for OR or Expression
     * @param mixed                 $operator Condition such as '=', '>' or 'is not'
     * @param string|Sql\Expression $value    Value. Will be quoted unless you pass expression
     *
     * @return $this
     */
    public function having($field, $operator = null, $value = null)
    {
        return $this->where(...func_get_args());
    }

    /**
     * Subroutine which renders conditions.
     *
     * @return Sql\Expression[]
     */
    protected function getConditionExpressionsList(): array
    {
        $ret = [];

        // where() might have been called multiple times. Collect all conditions
        foreach ($this->conditions as $conditionArgs) {
            $ret[] = $this->getConditionExpression($conditionArgs);
        }

        return $ret;
    }

    protected function getConditionExpression($conditionArgs)
    {
        if (count($conditionArgs) === 3) {
            [$field, $operator, $value] = $conditionArgs;
        } elseif (count($conditionArgs) === 2) {
            [$field, $operator] = $conditionArgs;
        } elseif (count($conditionArgs) === 1) {
            [$field] = $conditionArgs;
        } else {
            throw new \InvalidArgumentException();
        }

        $field = Sql\Expression::asIdentifier($field);

        if (count($conditionArgs) === 1) {
            // Only a single parameter was passed, so we simply include all
            return $field;
        }

        // below are only cases when 2 or 3 arguments are passed

        // if no condition defined - set default condition
        if (count($conditionArgs) === 2) {
            $value = $operator; // @phpstan-ignore-line see https://github.com/phpstan/phpstan/issues/4173

            if ($value instanceof Sql\Expressionable) {
                $value = $value->toSqlExpression();
            }

            if (is_array($value)) {
                $operator = 'in';
            } elseif ($value instanceof Sql\Expression && $value->selectsMultipleRows()) {
                $operator = 'in';
            } else {
                $operator = '=';
            }
        } else {
            $operator = trim(strtolower($operator)); // @phpstan-ignore-line see https://github.com/phpstan/phpstan/issues/4173
        }

        // below we can be sure that all 3 arguments has been passed

        // special conditions (IS | IS NOT) if value is null
        if ($value === null) { // @phpstan-ignore-line see https://github.com/phpstan/phpstan/issues/4173
            $operator = in_array($operator, ['!=', '<>', 'not', 'not in', 'is not'], true) ? 'is not' : 'is';

            return new Sql\Expression("{field} {$operator} null", compact('field'));
        }

        // value should be array for such conditions
        if (in_array($operator, ['in', 'not in', 'not'], true) && is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        // special conditions (IN | NOT IN) if value is array
        if (is_array($value)) {
            $operator = in_array($operator, ['!=', '<>', 'not', 'not in', 'is not'], true) ? 'not in' : 'in';

            // special treatment of empty array condition
            if (empty($value)) {
                return $operator === 'in' ?
                    new Sql\Expression('1 = 0') : // never true
                    new Sql\Expression('1 = 1'); // always true
            }

            $value = Sql\Expression::asParameterList($value)->consumedInParentheses();
        }

        return new Sql\Expression("{{field}} {$operator} [value]", compact('field', 'value'));
    }

    protected function _render_conditions()
    {
        if ($this->conditions) {
            return Sql\Expression::asParameterList($this->getConditionExpressionsList(), $this->junction);
        }
    }
}
