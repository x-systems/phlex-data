<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Expression;

use Phlex\Data\Persistence\Sql;

class Union extends Sql\Expression
{
    protected $template = '[tables]';

    /** @var string */
    protected $junction = ' union all ';

    /** @var array<Sql\Expression> */
    protected $tables = [];

    public function __construct(array $tables)
    {
        foreach ($tables as $table) {
            if (is_string($table)) {
                $table = (new Sql\Statement())->table($table);
            }

            $this->tables[] = $table;
        }
    }

    protected function _render_tables()
    {
        if ($this->tables) {
            return Sql\Expression::asParameterList($this->getTableExpressionsList(), $this->junction, true);
        }
    }

    protected function getTableExpressionsList(): array
    {
        $ret = [];

        foreach ($this->tables as $table) {
            $ret[] = $table->consumedInParentheses(false);
        }

        return $ret;
    }
}
