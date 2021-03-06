<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

use Doctrine\DBAL\Types\Types;
use Phlex\Data\Persistence\Sql;

class Integer extends Sql\Codec
{
    protected $columnTypeName = Types::INTEGER;

    protected function doEncode($value)
    {
        return (int) $value;
    }
}
