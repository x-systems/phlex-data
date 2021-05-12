<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Oracle\Codec;

use Phlex\Data\Persistence\Sql;

class Object_ extends Sql\Codec\Object_
{
    protected function doDecode($value)
    {
        // for Oracle CLOB/BLOB datatypes
        if (is_resource($value) && get_resource_type($value) === 'stream') {
            $value = stream_get_contents($value);
        }

        return parent::doDecode($value);
    }
}
