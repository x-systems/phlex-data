<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

use Phlex\Data\Persistence\Sql;

class Dynamic extends Sql\Codec
{
    protected $encodeFx;

    protected $decodeFx;

    protected function doEncode($value)
    {
        return isset($this->encodeFx) ? ($this->encodeFx)($value, $this->field) : $value;
    }

    protected function doDecode($value)
    {
        return isset($this->decodeFx) ? ($this->decodeFx)($value, $this->field) : $value;
    }
}
