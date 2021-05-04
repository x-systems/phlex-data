<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

use Doctrine\DBAL\Types\Types;
use Phlex\Data\Persistence\Sql;

class Float_ extends Sql\Codec
{
    protected $columnTypeName = Types::FLOAT;

    protected function doEncode($value)
    {
        return (float) $value;
    }
}
