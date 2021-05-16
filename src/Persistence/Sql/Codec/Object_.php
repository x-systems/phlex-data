<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

use Doctrine\DBAL\Types\Types;
use Phlex\Data\Persistence\Sql;

class Object_ extends Sql\Codec
{
    protected $columnTypeName = Types::OBJECT;

    protected function doEncode($value)
    {
        return self::jsonEncode($value);
    }

    protected function doDecode($value)
    {
        if (is_resource($value) && get_resource_type($value) === 'stream') {
            $value = stream_get_contents($value);
        }

        return self::jsonDecode($value);
    }
}
