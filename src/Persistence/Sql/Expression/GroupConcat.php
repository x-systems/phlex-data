<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Expression;

use Phlex\Data\Persistence\Sql;

/**
 * Creates expression for a function, which can be used as part of the GROUP
 * query which would concatenate all matching columns.
 *
 * MySQL, SQLite - group_concat
 * PostgreSQL - string_agg
 * Oracle - listagg
 */
class GroupConcat extends Sql\Expression
{
    protected $template = 'group_concat({column}, [delimiter])';

    /**
     * @param mixed $column
     */
    public function __construct($column, string $delimiter = ',')
    {
        $this->args['custom'] = compact('column', 'delimiter');
    }
}
