<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Sqlite;

use Phlex\Data\Persistence\Sql;

class Statement extends Sql\Statement
{
    protected $template_truncate = 'delete [from] [table_noalias]';

    public function groupConcat($field, string $delimiter = ',')
    {
        return $this->expr('group_concat({}, [])', [$field, $delimiter]);
    }
}
