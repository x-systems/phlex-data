<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Sqlite;

use Phlex\Data\Persistence\Sql;

class Query extends Sql\Query
{
    protected static $templates = [
        self::MODE_TRUNCATE => 'delete [from] [table_noalias]',
    ];
}
