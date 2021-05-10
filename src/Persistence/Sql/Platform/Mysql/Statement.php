<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Mysql;

use Phlex\Data\Persistence\Sql;

class Statement extends Sql\Statement
{
    protected $template_update = 'update [table][join] set [set] [where]';
}
