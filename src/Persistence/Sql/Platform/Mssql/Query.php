<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Mssql;

use Phlex\Data\Persistence\Sql;

class Query extends Sql\Query
{
    protected static $templates = [
        self::MODE_INSERT => 'begin try'
            . "\n" . 'insert[option] into [table_noalias] ([set_fields]) values ([set_values])'
            . "\n" . 'end try begin catch if ERROR_NUMBER() = 544 begin'
            . "\n" . 'set IDENTITY_INSERT [table_noalias] on'
            . "\n" . 'insert[option] into [table_noalias] ([set_fields]) values ([set_values])'
            . "\n" . 'set IDENTITY_INSERT [table_noalias] off'
            . "\n" . 'end end catch',
    ];
}
