<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql;

use Phlex\Core\InjectableTrait;
use Phlex\Data\Exception;
use Phlex\Data\Persistence;

class Expression implements Expressionable, \ArrayAccess, \IteratorAggregate
{
    use InjectableTrait;

    /** @const string "[]" in template, escape as parameter */
    protected const ESCAPE_PARAM = 'param';
    /** @const string "{}" in template, escape as identifier */
    protected const ESCAPE_IDENTIFIER = 'identifier';
    /** @const string "{{}}" in template, escape as identifier, but keep input with special characters like "." or "(" unescaped */
    protected const ESCAPE_IDENTIFIER_SOFT = 'identifier-soft';
    /** @const string keep input as is */
    protected const ESCAPE_NONE = 'none';

    /** @var string|array<int|string, string> */
    protected $template;

    /**
     * Configuration accumulated by calling methods such as Query::field(), Query::table(), etc.
     *
     * $args['custom'] is used to store hash of custom template replacements.
     *
     * This property is made public to ease customization and make it accessible
     * from Connection class for example.
     *
     * @var array
     */
    public $args = ['custom' => []];

    /**
     * As per PDO, escapeParam() will convert value into :a, :b, :c .. :aa .. etc.
     *
     * @var string
     */
    protected $nextPlaceholder = 'a';

    public $identifierQuoteCharacter = '"';

    /** @var array Populated with actual values by escapeParam() */
    public $params = [];

    /** @var Persistence\Sql|null */
    public $persistence;

    /** @var bool Wrap the expression in parentheses when consumed by another expression or not. */
    protected $consumedInParentheses = false;

    /** @var bool Flag to indicate if result from expression can be a subset of rows. */
    protected $selectsMultipleRows = false;

    /**
     * Map of Persistence specific render methods for expression tags.
     *
     * @var array<int|string, array>
     */
    protected static $tagRenderMethods = [];

    /**
     * Specifying options to constructors will override default
     * attribute values of this class.
     *
     * If $properties is passed as string, then it's treated as template.
     *
     * @param string|array $properties
     * @param array        $arguments
     */
    public function __construct($properties = [], $arguments = null)
    {
        // save template
        if (is_string($properties)) {
            $properties = ['template' => $properties];
        } elseif (!is_array($properties)) {
            throw (new Exception('Incorrect use of Expression constructor'))
                ->addMoreInfo('properties', $properties)
                ->addMoreInfo('arguments', $arguments);
        }

        // supports passing template as property value without key 'template'
        if (isset($properties[0])) {
            $properties['template'] = $properties[0];
            unset($properties[0]);
        }

        // save arguments
        if ($arguments !== null) {
            if (!is_array($arguments)) {
                throw (new Exception('Expression arguments must be an array'))
                    ->addMoreInfo('properties', $properties)
                    ->addMoreInfo('arguments', $arguments);
            }
            $this->args['custom'] = $arguments;
        }

        // deal with remaining properties
        foreach ($properties as $key => $val) {
            $this->{$key} = $val;
        }
    }

    /**
     * Whether or not an offset exists.
     *
     * @param string $offset
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->args['custom']);
    }

    /**
     * Returns the value at specified offset.
     *
     * @param string $offset
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->args['custom'][$offset];
    }

    /**
     * Assigns a value to the specified offset.
     *
     * @param string $offset
     * @param mixed  $value  The value to set
     */
    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->args['custom'][] = $value;
        } else {
            $this->args['custom'][$offset] = $value;
        }
    }

    /**
     * Unsets an offset.
     *
     * @param string $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->args['custom'][$offset]);
    }

    public function getIterator(): \Traversable
    {
        return $this->execute()->iterateAssociative();
    }

    /**
     * Resets arguments.
     *
     * @param string $tag
     *
     * @return $this
     */
    public function reset($tag = null)
    {
        // unset all arguments
        if ($tag === null) {
            $this->args = ['custom' => []];

            return $this;
        }

        if (!is_string($tag)) {
            throw (new Exception('Tag should be string'))
                ->addMoreInfo('tag', $tag);
        }

        // unset custom/argument or argument if such exists
        if ($this->offsetExists($tag)) {
            $this->offsetUnset($tag);
        } elseif (isset($this->args[$tag])) {
            unset($this->args[$tag]);
        }

        return $this;
    }

    public function getIdentifierQuoteCharacter()
    {
        if ($this->persistence && $this->persistence->connection) {
            return $this->persistence->connection->getDatabasePlatform()->getIdentifierQuoteCharacter();
        }

        return $this->identifierQuoteCharacter;
    }

    /**
     * Converts value into parameter and returns reference. Use only during
     * query rendering. Consider using `render()` instead, which will
     * also handle nested expressions properly.
     *
     * @param string|int|float $value
     *
     * @return string Name of parameter
     */
    protected function escapeParam($value): string
    {
        $name = ':' . $this->nextPlaceholder++;

        $this->params[$name] = $value;

        return $name;
    }

    /**
     * Escapes argument by adding backticks around it.
     * This will allow you to use reserved SQL words as table or field
     * names such as "table" as well as other characters that SQL
     * permits in the identifiers (e.g. spaces or equation signs).
     */
    protected function escapeIdentifier(string $value): string
    {
        // in all other cases we should escape
        $c = $this->getIdentifierQuoteCharacter();

        return $c . str_replace($c, $c . $c, $value) . $c;
    }

    /**
     * Soft-escaping SQL identifier. This method will attempt to put
     * escaping char around the identifier, however will not do so if you
     * are using special characters like ".", "(" or escaping char.
     *
     * It will smartly escape table.field type of strings resulting
     * in "table"."field".
     */
    protected function escapeIdentifierSoft(string $value): string
    {
        // in some cases we should not escape
        if ($this->isUnescapablePattern($value)) {
            return $value;
        }

        if (strpos($value, '.') !== false) {
            return implode('.', array_map(__METHOD__, explode('.', $value)));
        }

        $c = $this->getIdentifierQuoteCharacter();

        return $c . trim($value) . $c;
    }

    /**
     * Given the string parameter, it will detect some "deal-breaker" for our
     * soft escaping, such as "*" or "(".
     * Those will typically indicate that expression is passed and shouldn't
     * be escaped.
     */
    protected function isUnescapablePattern($value)
    {
        return is_object($value)
            || $value === '*'
            || strpos($value, '(') !== false
            || strpos($value, $this->getIdentifierQuoteCharacter()) !== false;
    }

    /**
     * Render self and return a string using placeholders.
     *
     * The params resolved are stored under Expression::$params property
     */
    public function render(): string
    {
        $expression = clone $this;

        $backupNextPlaceholder = $this->nextPlaceholder;

        $result = $this->consume($expression->consumedInParentheses(false));

        $this->nextPlaceholder = $backupNextPlaceholder;

        return trim($result);
    }

    /**
     * Render $expressionable and return it as string.
     *
     * In case $expressionable is a string then $escapeMode is used to escape the result
     */
    public function consume($expressionable, ?string $escapeMode = self::ESCAPE_PARAM): string
    {
        if (!is_object($expressionable)) {
            switch ($escapeMode) {
                case self::ESCAPE_PARAM:
                    return $this->escapeParam($expressionable);
                case self::ESCAPE_IDENTIFIER:
                    return $this->escapeIdentifier($expressionable);
                case self::ESCAPE_IDENTIFIER_SOFT:
                    return $this->escapeIdentifierSoft($expressionable);
                case self::ESCAPE_NONE:
                case null:
                    return (string) $expressionable;
            }

            throw (new Exception('$escapeMode value is incorrect'))
                ->addMoreInfo('escapeMode', $escapeMode);
        }

        /** @var Expressionable $expressionable */
        $expression = $expressionable->toSqlExpression()->getConsumable($this);

        $template = $expression->template;
        if (is_array($template)) {
            $templateOption = $this->persistence ? get_class($this->persistence) : 0;

            $template = $expression->template[$templateOption] ?? null;
        } elseif (is_string($template) && substr($template, 0, 1) === '@') {
            $name = substr($template, 1);
            $template = $this->{'template_' . $name} ?? $expression->{'template_' . $name} ?? $template;
        }

        if ($template === null) {
            throw new Exception('Template is not defined for Expression');
        }

        $namelessCount = 0;

        // - [xxx] = param
        // - {xxx} = escape
        // - {{xxx}} = escapeSoft
        $result = /* trim( */ preg_replace_callback(
            <<<'EOF'
                ~
                 '(?:[^'\\]+|\\.|'')*'\K
                |"(?:[^"\\]+|\\.|"")*"\K
                |`(?:[^`\\]+|\\.|``)*`\K
                |\[\w*\]
                |\{\w*\}
                |\{\{\w*\}\}
                ~xs
                EOF,
            function ($matches) use (&$namelessCount, $expression) {
                if ($matches[0] === '') {
                    return '';
                }

                [$identifier, $escaping] = $this->decodeIdentifier($matches[0]);

                // allow template to contain []
                if ($identifier === '') {
                    $identifier = $namelessCount++;

                    // use rendering only with named tags
                }

                $fx = $expression->getRenderMethod($identifier, $this->persistence ? get_class($this->persistence) : null);

                // [foo] will attempt to call $this->_render_foo() or $expression->_render_foo()

                if (array_key_exists($identifier, $expression->args['custom'])) {
                    $value = $this->consume($expression->args['custom'][$identifier], $escaping);
                } elseif (method_exists($this, $fx)) {
                    $args = $this->args;
                    $this->args = $expression->args;
                    $value = $this->{$fx}();
                    $this->args = $args;
                } elseif (method_exists($expression, $fx)) {
                    $value = $expression->{$fx}();
                } else {
                    throw (new Exception('Expression could not render tag'))
                        ->addMoreInfo('tag', $identifier);
                }

                if (is_array($value)) {
                    $format = '(%s)';
                } else {
                    $value = [$value];
                    $format = '%s';
                }

                $list = [];
                foreach ($value as $expressionableValue) {
                    $list[] = $this->consume($expressionableValue, self::ESCAPE_NONE);
                }

                return sprintf($format, implode(',', $list));
            },
            $template
        )/* ) */;

        // Wrap in parentheses if expression requires so
        if ($expression->consumedInParentheses === true && trim($result)) {
            $result = '(' . trim($result) . ')';
        }

        return $result;
    }

    protected function getConsumable(self $consumer): self
    {
        $this->identifierQuoteCharacter = $consumer->getIdentifierQuoteCharacter();

        $expression = $this;
        if ($this->persistence = $consumer->persistence) {
            $expression = $this->persistence->expression($this);
        }

        return $expression;
    }

    public function getSeedDefaults(): array
    {
        $ret = [];

        foreach ($this->getSeedProperties() as $property) {
            $ret[$property] = $this->{$property};
        }

        return $ret;
    }

    protected function getSeedProperties(): array
    {
        return [
            'args',
            'nextPlaceholder',
            'identifierQuoteCharacter',
            'params',
            'consumedInParentheses',
            'selectsMultipleRows',
        ];
    }

    /**
     * Create expression where items in the list are escaped as parameters.
     */
    public static function asParameterList(array $list, string $separator = ',', bool $wrapInParentheses = false): self
    {
        return self::asEscapedList('[]', $list, $separator, $wrapInParentheses);
    }

    /**
     * Create expression where items in the list are escaped as identifiers.
     */
    public static function asIdentifierList(array $list, string $separator = ',', bool $wrapInParentheses = false): self
    {
        return self::asEscapedList('{}', $list, $separator, $wrapInParentheses);
    }

    /**
     * Create expression where items in the list are escaped as soft identifiers.
     */
    public static function asIdentifierSoftList(array $list, string $separator = ',', bool $wrapInParentheses = false): self
    {
        return self::asEscapedList('{{}}', $list, $separator, $wrapInParentheses);
    }

    /**
     * Create expression where items in the list are escaped depending on $tag.
     */
    public static function asEscapedList(string $tag, array $list, string $separator = ',', bool $wrapInParentheses = false): self
    {
        $template = implode($separator, array_fill(0, count($list), $tag));
        if ($wrapInParentheses) {
            $template = '(' . $template . ')';
        }

        return new self($template, $list);
    }

    /**
     * Create expression representing identifier $expressionable with $alias.
     *
     * @param string|Expressionable $expressionable
     */
    public static function asIdentifier($expressionable, string $alias = null): self
    {
        return $alias ?
            new self('{{}} {}', [$expressionable, $alias]) :
            new self('{{}}', [$expressionable]);
    }

    public function execute(Persistence\Sql $persistence = null)
    {
        $persistence ??= $this->persistence;

        if (!$persistence) {
            throw new Exception('Expression can only be executed when persistence is specified');
        }

        return $persistence->execute($this);
    }

    public static function getRenderMethod($tag, $persistenceClass = null)
    {
        if ($persistenceClass && isset(static::$tagRenderMethods[$persistenceClass][$tag])) {
            return static::$tagRenderMethods[$persistenceClass][$tag];
        }

        return '_render_' . $tag;
    }

    /**
     * Decode the identifier and escaping required from a template.
     *
     * [xxx] = param
     * {xxx} = escape
     * {{xxx}} = escapeSoft
     * {[xxx]} = escapeNone
     */
    protected static function decodeIdentifier(string $identifierTemplate): array
    {
        $identifier = substr($identifierTemplate, 1, -1);

        $escaping = null;
        if (substr($identifierTemplate, 0, 1) === '[') {
            $escaping = self::ESCAPE_PARAM;
        } elseif (substr($identifierTemplate, 0, 1) === '{') {
            if (substr($identifierTemplate, 1, 1) === '{') {
                $escaping = self::ESCAPE_IDENTIFIER_SOFT;
                $identifier = substr($identifier, 1, -1);
            } elseif (substr($identifierTemplate, 1, 1) === '[') {
                $escaping = self::ESCAPE_NONE;
            } else {
                $escaping = self::ESCAPE_IDENTIFIER;
            }
        }

        return [$identifier, $escaping];
    }

    /**
     * Sets the flag if the expression should be wrapped in parantheses when consumed by other expression.
     */
    public function consumedInParentheses(bool $flag = true)
    {
        $this->consumedInParentheses = $flag;

        return $this;
    }

    public function selectsMultipleRows()
    {
        return $this->selectsMultipleRows;
    }

    public static function and(): Expression\Condition
    {
        return new Expression\Condition(Expression\Condition::JUNCTION_AND);
    }

    public static function or(): Expression\Condition
    {
        return new Expression\Condition(Expression\Condition::JUNCTION_OR);
    }

    /**
     * Returns Expression\Case_ object.
     *
     * @param mixed $operand optional operand for case expression
     */
    public function case($operand = null): Expression\Case_
    {
        return new Expression\Case_($operand);
    }

    /**
     * Return formatted debug SQL query.
     */
    public function getDebugQuery(): string
    {
        $result = $this->render();

        foreach (array_reverse($this->params) as $key => $val) {
            if (is_numeric($key)) {
                continue;
            }

            if (is_numeric($val)) {
                $replacement = $val . '\1';
            } elseif (is_string($val)) {
                $replacement = "'" . addslashes($val) . "'\\1";
            } elseif ($val === null) {
                $replacement = 'NULL\1';
            } else {
                $replacement = $val . '\\1';
            }

            $result = preg_replace('~' . $key . '([^_]|$)~', $replacement, $result);
        }

        return $result;
    }

    public function getDebugQueryFormatted(): string
    {
        $result = self::getDebugQuery();

        return class_exists(\SqlFormatter::class) ? // requires optional "jdorn/sql-formatter" package
            \SqlFormatter::format($result, false) :
            $result;
    }

    public function __toString()
    {
        return $this->render();
    }

    public function __debugInfo()
    {
        $arr = [
            'R' => false,
            'template' => $this->template,
            'params' => $this->params,
            'args' => $this->args,
        ];

        try {
            $arr['R'] = $this->getDebugQuery();
        } catch (\Exception $e) {
            $arr['R'] = $e->getMessage();
        }

        return $arr;
    }

    public function toSqlExpression(): self
    {
        return $this;
    }
}
