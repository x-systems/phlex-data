<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

use Doctrine\DBAL\Types\Types;
use Phlex\Data\Persistence\Sql;

class Object_ extends Sql\Codec
{
    protected $columnTypeName = Types::OBJECT;

    public function encode($value)
    {
        return self::jsonEncode($value);
    }

    public function decode($value)
    {
        return self::jsonDecode($value);
    }
}
