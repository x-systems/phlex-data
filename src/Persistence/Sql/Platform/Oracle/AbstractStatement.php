<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Oracle;

use Phlex\Data\Persistence\Sql;

abstract class AbstractStatement extends Sql\Statement
{
    /** @var string */
    protected $template_seq_currval = 'select [sequence].CURRVAL from dual';
    /** @var string */
    protected $template_seq_nextval = '[sequence].NEXTVAL';

    public function render()
    {
        if ($this->mode === 'select' && $this->main_table === null) {
            $this->table('DUAL');
        }

        return parent::render();
    }

    /**
     * Set sequence.
     *
     * @param string $sequence
     *
     * @return $this
     */
    public function sequence($sequence)
    {
        $this->args['sequence'] = $sequence;

        return $this;
    }

    public function _render_sequence()
    {
        return $this->args['sequence'];
    }
}
