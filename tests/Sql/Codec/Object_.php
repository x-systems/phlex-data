<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\SQL\Codec;

use Doctrine\DBAL\Types\Types;
use Phlex\Data\Persistence\SQL;

class Object_ extends SQL\Codec
{
    protected $columnTypeName = Types::OBJECT;

    protected function doEncode($value)
    {
        return self::jsonEncode($value);
    }

    protected function doDecode($value)
    {
        return self::jsonDecode($value);
    }
}
