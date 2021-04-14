<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence;

class Codec
{
    public function encode($value)
    {
        return $value;
    }

    public function decode($value)
    {
        return $value;
    }

    public function getQueryArguments($field, $operator, $value): array
    {
        return [$field, $operator, $value];
    }
}
