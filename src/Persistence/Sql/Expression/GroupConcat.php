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
    protected $template = [
        '[group_concat]',
        Sql\Platform\Sqlite::class => 'group_concat({column}, [delimiter])',
        Sql\Platform\Mssql::class => 'string_agg({column}, [delimiter])',
        Sql\Platform\Mysql::class => 'group_concat({column} separator [delimiter])',
        Sql\Platform\Oracle::class => 'listagg({column}, [delimiter]) within group (order by {column})',
        Sql\Platform\Postgresql::class => 'string_agg({column}, [delimiter])',
    ];

    /**
     * @param mixed $column
     */
    public function __construct($column, string $delimiter = ',')
    {
        $this->args['custom'] = compact('column', 'delimiter');
    }
}
