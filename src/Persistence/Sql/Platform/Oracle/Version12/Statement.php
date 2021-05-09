<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Oracle\Version12;

use Phlex\Data\Persistence\Sql\Platform\Oracle;

class Statement extends Oracle\AbstractStatement
{
    public function _render_limit()
    {
        if (isset($this->args['limit'])) {
            $cnt = (int) $this->args['limit']['cnt'];
            $shift = (int) $this->args['limit']['shift'];

            return ' ' . trim(
                ($shift ? 'OFFSET ' . $shift . ' ROWS' : '') .
                ' ' .
                // as per spec 'NEXT' is synonymous to 'FIRST', so not bothering with it.
                // https://docs.oracle.com/javadb/10.8.3.0/ref/rrefsqljoffsetfetch.html
                ($cnt ? 'FETCH NEXT ' . $cnt . ' ROWS ONLY' : '')
            );
        }
    }
}
