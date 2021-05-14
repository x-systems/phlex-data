<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql;

interface Expressionable
{
    public function toSqlExpression(): Expression;
}
