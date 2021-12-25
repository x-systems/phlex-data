<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform;

use Phlex\Data\Persistence;

class Mssql extends Persistence\Sql
{
    protected $seeds = [
        Persistence\Sql\Statement::class => [Mssql\Statement::class],
    ];
}
