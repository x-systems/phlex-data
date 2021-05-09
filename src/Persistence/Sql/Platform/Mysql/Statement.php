<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Mysql;

use Phlex\Data\Persistence\Sql;

class Statement extends Sql\Statement
{
    protected $template_update = 'update [table][join] set [set] [where]';

    public function groupConcat($field, string $delimiter = ',')
    {
        return $this->expr('group_concat({} separator [])', [$field, $delimiter]);
    }
}
