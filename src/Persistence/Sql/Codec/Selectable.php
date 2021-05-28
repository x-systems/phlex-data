<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

use Doctrine\DBAL\Types\Types;
use Phlex\Data\Persistence\Sql\Expression;

class Selectable extends Object_
{
    protected $columnTypeName = Types::JSON;

    public function getQueryArguments($operator, $value): array
    {
        $expr = Expression::or();
        
        foreach ((array) $value as $v) {
            $expr->where(Expression::jsonContains($this->field, $v));
        }

        return [$expr];
    }
}
