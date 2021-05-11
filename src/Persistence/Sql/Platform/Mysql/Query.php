<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Mysql;

use Phlex\Data\Persistence\Sql;

class Query extends Sql\Query
{
    protected static $templates = [
        self::MODE_UPDATE => 'update [table][join] set [set] [where]',
    ];
}
