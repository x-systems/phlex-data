<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Oracle;

class Statement extends AbstractStatement
{
    // {{{ for Oracle 11 and lower to support LIMIT with OFFSET

    protected $template_select = '[with]select[option] [field] [from] [table][join][where][group][having][order]';
    protected $template_select_limit = 'select * from (select "__t".*, rownum "__dsql_rownum" from ([with]select[option] [field] [from] [table][join][where][group][having][order]) "__t") where "__dsql_rownum" > [limit_start][and_limit_end]';

    public function limit($cnt, $shift = null)
    {
        // This is for pre- 12c version
        $this->template_select = $this->template_select_limit;

        return parent::limit($cnt, $shift);
    }

    public function _render_limit_start()
    {
        return (int) $this->args['limit']['shift'];
    }

    public function _render_and_limit_end()
    {
        if (!$this->args['limit']['cnt']) {
            return '';
        }

        return ' and "__dsql_rownum" <= ' .
            max((int) ($this->args['limit']['cnt'] + $this->args['limit']['shift']), (int) $this->args['limit']['cnt']);
    }
}
