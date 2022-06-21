<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Expression;

use Phlex\Data\Persistence\Sql;

class Now extends Sql\Expression
{
    protected $template = '[now]';

    /** @var int */
    protected $precision;

    public function __construct(int $precision = null)
    {
        $this->precision = $precision;
    }

    public function _render_now(): Sql\Expression
    {
        return new Sql\Expression(
            'current_timestamp(' . ($this->precision !== null ? '[]' : '') . ')',
            $this->precision !== null ? [$this->precision] : []
        );
    }
}
