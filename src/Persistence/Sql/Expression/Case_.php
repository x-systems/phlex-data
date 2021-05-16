<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Expression;

use Phlex\Data\Exception;
use Phlex\Data\Persistence\Sql;

class Case_ extends Sql\Expression
{
    protected $template = '[case]';

    public function __construct($operand = null)
    {
        if ($operand !== null) {
            $this->args['case_operand'] = $operand;
        }
    }

    /**
     * Add when/then condition for [case] expression.
     *
     * @param mixed $when Condition as array for normal form [case] statement or just value in case of short form [case] statement
     * @param mixed $then Then expression or value
     *
     * @return $this
     */
    public function when($when, $then)
    {
        $this->args['case_when'][] = [$when, $then];

        return $this;
    }

    /**
     * Add else condition for [case] expression.
     *
     * @param mixed $else Else expression or value
     *
     * @return $this
     */
    public function else($else)
    {
        $this->args['case_else'] = $else;

        return $this;
    }

    protected function _render_case()
    {
        if (!isset($this->args['case_when'])) {
            return;
        }

        $template = ' case';
        $args = [];

        // operand
        if ($shortForm = isset($this->args['case_operand'])) {
            $template .= ' {{operand}}';
            $args['operand'] = $this->args['case_operand'];
        }

        // when, then
        foreach ($this->args['case_when'] as [$when, $then]) {
            if ($shortForm) {
                // short-form
                if (is_array($when)) {
                    throw (new Exception('When using short form CASE statement, then you should not set array as when() method 1st parameter'))
                        ->addMoreInfo('when', $when);
                }
            } else {
                $when = Condition::and()->where(...$when);
            }

            $template .= ' when [] then []';
            $args[] = $when;
            $args[] = $then;
        }

        // else
        if (array_key_exists('case_else', $this->args)) {
            $template .= ' else []';
            $args[] = $this->args['case_else'];
        }

        $template .= ' end';

        return new Sql\Expression($template, $args);
    }
}
