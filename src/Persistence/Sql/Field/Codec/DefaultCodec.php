<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Field\Codec;

use Phlex\Data\Persistence\Codec;

class DefaultCodec extends Codec
{
    public function encode($value)
    {
        return (string) $value;
    }
}
