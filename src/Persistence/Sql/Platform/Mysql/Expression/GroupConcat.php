<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Mysql\Expression;

use Phlex\Data\Persistence\Sql;

class GroupConcat extends Sql\Expression\GroupConcat
{
    protected $template = 'group_concat({column} separator [delimiter])';
}
