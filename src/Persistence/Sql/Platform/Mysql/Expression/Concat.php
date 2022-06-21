<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Mysql\Expression;

use Phlex\Data\Persistence\Sql;

class Concat extends Sql\Expression\Concat
{
    protected function _render_concat()
    {
        return new Sql\Expression('concat([])', [Sql\Expression::asParameterList($this->args['custom'])]);
    }
}
