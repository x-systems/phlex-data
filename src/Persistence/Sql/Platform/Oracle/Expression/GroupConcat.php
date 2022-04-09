<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Oracle\Expression;

use Phlex\Data\Persistence\Sql;

class GroupConcat extends Sql\Expression\GroupConcat
{
    protected $template = 'listagg({column}, [delimiter]) within group (order by {column})';
}
