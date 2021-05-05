<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\SQL\Codec;

use Phlex\Data\Persistence\SQL;

class Dynamic extends SQL\Codec
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
