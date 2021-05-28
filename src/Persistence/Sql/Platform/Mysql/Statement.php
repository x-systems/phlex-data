<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Mysql;

use Phlex\Data\Persistence\Sql;

class Statement extends Sql\Statement
{
    protected $template_update = 'update [table][join] set [set] [where]';

    protected function _render_concat()
    {
        return new Sql\Expression('concat([])', [Sql\Expression::asParameterList($this->args['custom'])]);
    }

    protected function _render_group_concat()
    {
        return new Sql\Expression('group_concat({field} separator [delimiter])', $this->args['custom']);
    }
    
    protected function _render_json_contains()
    {
        return new Sql\Expression('json_contains({field}, [value], [path])', $this->args['custom']);
    }
}
