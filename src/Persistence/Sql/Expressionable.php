<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql;

use Phlex\Data\Persistence;

interface Expressionable
{
    public function toExpression(Persistence\Sql $persistence): Expression;
}
