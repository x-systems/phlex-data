<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Mssql\Expression;

use Phlex\Data\Persistence\Sql;

class GroupConcat extends Sql\Expression\GroupConcat
{
    protected $template = 'string_agg({column}, [delimiter])';
}
