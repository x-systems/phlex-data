<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

use Phlex\Data\Persistence\Sql;

class Dynamic extends Sql\Codec
{
    protected $encodeFx;

    protected $decodeFx;

    public function encode($value)
    {
        return isset($this->encodeFx) ? ($this->encodeFx)($value, $this->field) : $value;
    }

    public function decode($value)
    {
        return isset($this->decodeFx) ? ($this->decodeFx)($value, $this->field) : $value;
    }
}
