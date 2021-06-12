<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Expression;

use Phlex\Data\Persistence\Sql;

/**
 * Returns an expression for checling if json column contains value.
 *
 * new Sql\Expression\Concat(Sql\Expression::asIdentifier('abc'), ' ', Sql\Expression::asIdentifier('cde'))
 */
class JsonContains extends Sql\Expression
{
    protected $template = [
        'json column {column} contains [value] on path [path]',
        Sql\Platform\Mssql::class => 'json_contains({column}, [value], [path])',
        Sql\Platform\Mysql::class => 'json_contains({column}, [value], [path])',
        Sql\Platform\Postgresql::class => '{column}::jsonb @? {[path]} ? (@ > [value])',
    ];

    public function __construct($column, $value, $path = '$')
    {
        $value = json_encode($value);

        $this->args['custom'] = compact('column', 'value', 'path');
    }
}
