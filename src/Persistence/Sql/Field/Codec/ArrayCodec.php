<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Field\Codec;

use Phlex\Data\Persistence\Codec;

class ArrayCodec extends Codec
{
    public function encode($value)
    {
        return json_encode($value);
    }

    public function decode($value)
    {
        return json_decode($value, true);
    }

    public function getQueryArguments($field, $operator, $value): array
    {
        return [$field, $operator, $value];
    }
}
