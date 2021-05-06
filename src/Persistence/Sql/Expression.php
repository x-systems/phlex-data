<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql;

class Expression implements Expressionable
{
    protected $template;
    
    public function toExpression(): Expression
    {
        return $this;
    }
    
    public function render(Interpreter $interpreter)
    {
        return preg_replace_callback(
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
            function ($matches) use (&$nameless_count) {
                if ($matches[0] === '') {
                    return '';
                }
                
                $identifier = substr($matches[0], 1, -1);
                
                $escaping = null;
                if (substr($matches[0], 0, 1) === '[') {
                    if (substr($matches[0], 1, 1) === '[') {
                        $escaping = self::ESCAPE_NONE;
                    }
                    else {
                        $escaping = self::ESCAPE_PARAM;
                    }
                } elseif (substr($matches[0], 0, 1) === '{') {
                    if (substr($matches[0], 1, 1) === '{') {
                        $escaping = self::ESCAPE_IDENTIFIER_SOFT;
                        $identifier = substr($identifier, 1, -1);
                    } else {
                        $escaping = self::ESCAPE_IDENTIFIER;
                    }
                }
                
                // allow template to contain []
                if ($identifier === '') {
                    $identifier = $nameless_count++;
                    
                    // use rendering only with named tags
                }
                $fx = '_render_' . $identifier;
                
                // [foo] will attempt to call $this->_render_foo()
                
                if (array_key_exists($identifier, $this->args['custom'])) {
                    $value = $this->consume($this->args['custom'][$identifier], $escaping);
                } elseif (method_exists($this, $fx)) {
                    $value = $this->{$fx}();
                } else {
                    throw (new Exception('Expression could not render tag'))
                    ->addMoreInfo('tag', $identifier);
                }
                
                return is_array($value) ? '(' . implode(',', $value) . ')' : $value;
            },
            $this->template
        );
    }
}
