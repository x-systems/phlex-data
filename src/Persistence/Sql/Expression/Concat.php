<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Expression;

use Phlex\Data\Persistence\Sql;

/**
 * Returns an expression for concatenating.
 *
 * new Sql\Expression\Concat(Sql\Expression::asIdentifier('abc'), ' ', Sql\Expression::asIdentifier('cde'))
 */
class Concat extends Sql\Expression
{
    protected $template = '[concat]';

    protected static $tagRenderMethods = [
        Sql\Platform\Mysql::class => [
            'concat' => '_render_concat_mysql',
        ],
    ];

    public function __construct(...$args)
    {
        $this->args['custom'] = $args;
    }

    protected function _render_concat()
    {
        return Sql\Expression::asParameterList($this->args['custom'], ' || ');
    }

    protected function _render_concat_mysql()
    {
        return new Sql\Expression('concat([])', [Sql\Expression::asParameterList($this->args['custom'])]);
    }
}
