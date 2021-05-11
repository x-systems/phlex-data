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

    // {{{ MSSQL does not support named parameters, so convert them to numerical inside execute

    private $numQueryParamsBackup;
    private $numQueryRender;

    /**
     * @return DbalResult|\PDOStatement PDOStatement iff for DBAL 2.x
     */
    public function execute(object $connection = null): object
    {
        if ($this->numQueryParamsBackup !== null) {
            return parent::execute($connection);
        }

        $this->numQueryParamsBackup = $this->params;
        try {
            $numParams = [];
            $i = 0;
            $j = 0;
            $this->numQueryRender = preg_replace_callback(
                '~(?:\'(?:\'\'|\\\\\'|[^\'])*\')?+\K(?:\?|:\w+)~s',
                function ($matches) use (&$numParams, &$i, &$j) {
                    $numParams[++$i] = $this->params[$matches[0] === '?' ? ++$j : $matches[0]];

                    return '?';
                },
                parent::render()
                );
            $this->params = $numParams;

            return parent::execute($connection);
        } finally {
            $this->params = $this->numQueryParamsBackup;
            $this->numQueryParamsBackup = null;
            $this->numQueryRender = null;
        }
    }

    public function render()
    {
        if ($this->numQueryParamsBackup !== null) {
            return $this->numQueryRender;
        }

        return parent::render();
    }

    public function getDebugQuery(): string
    {
        if ($this->numQueryParamsBackup === null) {
            return parent::getDebugQuery();
        }

        $paramsBackup = $this->params;
        $numQueryRenderBackupBackup = $this->numQueryParamsBackup;
        $numQueryRenderBackup = $this->numQueryRender;
        try {
            $this->params = $this->numQueryParamsBackup;
            $this->numQueryParamsBackup = null;
            $this->numQueryRender = null;

            return parent::getDebugQuery();
        } finally {
            $this->params = $paramsBackup;
            $this->numQueryParamsBackup = $numQueryRenderBackupBackup;
            $this->numQueryRender = $numQueryRenderBackup;
        }
    }
}
