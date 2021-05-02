<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

use Doctrine\DBAL\Types\Types;

class Array_ extends Object_
{
    protected $columnTypeName = Types::ARRAY;

//     public function getQueryArguments($operator, $value): array
//     {
// //         return [$field, $operator, $value];
//     }
}
