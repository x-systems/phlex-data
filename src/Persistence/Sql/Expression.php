<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql;

use Phlex\Data\Exception;
use Phlex\Data\Persistence;

class Expression implements Expressionable, \ArrayAccess, \IteratorAggregate
{
    /** @const string "[]" in template, escape as parameter */
    protected const ESCAPE_PARAM = 'param';
    /** @const string "{}" in template, escape as identifier */
    protected const ESCAPE_IDENTIFIER = 'identifier';
    /** @const string "{{}}" in template, escape as identifier, but keep input with special characters like "." or "(" unescaped */
    protected const ESCAPE_IDENTIFIER_SOFT = 'identifier-soft';
    /** @const string keep input as is */
    protected const ESCAPE_NONE = 'none';

    /** @var string */
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
    public $wrapInParentheses = false;

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

    /**
     * Returns Expression object.
     *
     * @param string|array $properties
     * @param array        $arguments
     */
    public function expr($properties = [], $arguments = null): self
    {
        $expr = new self($properties, $arguments);

        $expr->persistence = $this->persistence;

        return $expr;
    }

    public function getIdentifierQuoteCharacter()
    {
        if ($this->persistence && $this->persistence->connection) { // @phpstan-ignore-line
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

        $expression->wrapInParentheses = false;

        $backupNextPlaceholder = $this->nextPlaceholder;

        $result = $this->consume($expression);

        $this->nextPlaceholder = $backupNextPlaceholder;

        return $result;
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

        $expression = $expressionable->toSqlExpression();
        $expression->identifierQuoteCharacter = $this->getIdentifierQuoteCharacter();

        if ($expression->template === null) {
            throw new Exception('Template is not defined for Expression');
        }

        $template = $this->{'template_' . $expression->template} ?? $expression->{'template_' . $expression->template} ?? $expression->template;

        $namelessCount = 0;

        // - [xxx] = param
        // - {xxx} = escape
        // - {{xxx}} = escapeSoft
        $result = trim(preg_replace_callback(
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

                $fx = '_render_' . $identifier;

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
        ));

        // Wrap in parentheses if expression requires so
        if ($expression->wrapInParentheses === true) {
            $result = '(' . $result . ')';
        }

        return $result;
    }

    public function asList(array $list, string $separator = ',', $wrapInParantheses = false): self
    {
        $template = implode($separator, array_fill(0, count($list), '[]'));
        if ($wrapInParantheses) {
            $template = '(' . $template . ')';
        }

        return new self($template, $list);
    }

    public function execute(Persistence\Sql $persistence = null)
    {
        $persistence = $persistence ?? $this->persistence;

        if (!$persistence) {
            throw new Exception('Expression can only be executed when persistence is specified');
        }

        return $persistence->execute($this);
    }

    /**
     * Decode the identifier and escaping required from a template.
     *
     * [xxx] = param
     * {xxx} = escape
     * {{xxx}} = escapeSoft
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
            } else {
                $escaping = self::ESCAPE_IDENTIFIER;
            }
        }

        return [$identifier, $escaping];
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

        if (class_exists('SqlFormatter')) { // requires optional "jdorn/sql-formatter" package
            $result = \SqlFormatter::format($result, false);
        }

        return $result;
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
