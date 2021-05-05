<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\SQL\Codec;

use Doctrine\DBAL\Types\Types;
use Phlex\Data\Persistence\SQL;

class Float_ extends SQL\Codec
{
    protected $columnTypeName = Types::FLOAT;

    protected function doEncode($value)
    {
        return (float) $value;
    }
}
