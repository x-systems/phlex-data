<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Mssql;

use Phlex\Data\Persistence\Sql;

class Statement extends Sql\Statement
{
    use ExpressionTrait;

    protected $template_insert = 'begin try'
        . "\n" . 'insert[option] into [table_noalias] ([set_fields]) values ([set_values])'
        . "\n" . 'end try begin catch if ERROR_NUMBER() = 544 begin'
        . "\n" . 'set IDENTITY_INSERT [table_noalias] on'
        . "\n" . 'insert[option] into [table_noalias] ([set_fields]) values ([set_values])'
        . "\n" . 'set IDENTITY_INSERT [table_noalias] off'
        . "\n" . 'end end catch';

    public function _render_limit()
    {
        if (isset($this->args['limit'])) {
            $cnt = (int) $this->args['limit']['cnt'];
            $shift = (int) $this->args['limit']['shift'];

            return (!isset($this->args['order']) ? ' order by (select null)' : '')
                . ' offset ' . $shift . ' rows'
                . ' fetch next ' . $cnt . ' rows only';
        }
    }
}
