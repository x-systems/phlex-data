<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Mssql;

use Phlex\Data\Persistence\Sql;
use Phlex\Data\Persistence\Sql\Expression;

class Statement extends Sql\Statement
{
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

    protected function escapeIdentifier(string $value): string
    {
        return $this->fixOpenEscapeChar(parent::escapeIdentifier($value));
    }

    protected function escapeIdentifierSoft(string $value): string
    {
        return $this->fixOpenEscapeChar(parent::escapeIdentifierSoft($value));
    }

    private function fixOpenEscapeChar(string $v): string
    {
        return preg_replace('~(?:\'(?:\'\'|\\\\\'|[^\'])*\')?+\K\]([^\[\]\'"(){}]*?)\]~s', '[$1]', $v);
    }

    /**
     * MSSQL does not support named parameters, so convert them to numerical.
     */
    public function render(): string
    {
        $numParams = [];
        $i = 0;
        $j = 0;

        $result = preg_replace_callback(
            '~(?:\'(?:\'\'|\\\\\'|[^\'])*\')?+\K(?:\?|:\w+)~s',
            function ($matches) use (&$numParams, &$i, &$j) {
                $numParams[++$i] = $this->params[$matches[0] === '?' ? ++$j : $matches[0]];

                return '?';
            },
            parent::render()
        );

        $this->params = $numParams;

        return $result;
    }

    public function exists()
    {
        return (new static())->mode('select')->field(
            new Expression('case when exists[] then 1 else 0 end', [$this])
        );
    }
}
