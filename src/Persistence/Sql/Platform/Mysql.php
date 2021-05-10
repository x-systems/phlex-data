<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform;

use Phlex\Data\Persistence;

class Mysql extends Persistence\Sql
{
    public $_default_seed_statement = [Mysql\Statement::class];

    public function groupConcat($field, string $delimiter = ','): Persistence\Sql\Expression
    {
        return $this->expr('group_concat({} separator [])', [$field, $delimiter]);
    }
}
