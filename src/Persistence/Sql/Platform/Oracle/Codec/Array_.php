<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Oracle\Codec;

use Phlex\Data\Persistence\Sql;

class Array_ extends Sql\Codec\Array_
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
