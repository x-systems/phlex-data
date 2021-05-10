<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform;

use Phlex\Data\Persistence;

class Mssql extends Persistence\Sql
{
    public $_default_seed_statement = [Mssql\Statement::class];

    public function groupConcat($field, string $delimiter = ','): Persistence\Sql\Expression
    {
        return $this->expr('string_agg({}, \'' . $delimiter . '\')', [$field]);
    }
}
