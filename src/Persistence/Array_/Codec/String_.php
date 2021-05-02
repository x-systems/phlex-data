<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Array_\Codec;

use Phlex\Data\Persistence;

class String_ extends Persistence\Codec
{
    public function encode($value)
    {
        return $this->field->toString($value);
    }

    public function decode($value)
    {
        return $this->field->normalize($value);
    }
}
