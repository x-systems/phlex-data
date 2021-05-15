<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Oracle;

use Phlex\Data\Persistence\Sql;
use Phlex\Data\Persistence\Sql\Expression;

abstract class AbstractStatement extends Sql\Statement
{
    /** @var string */
    protected $template_seq_currval = 'select [sequence].CURRVAL from dual';
    /** @var string */
    protected $template_seq_nextval = '[sequence].NEXTVAL';

    public function render(): string
    {
        if ($this->mode === 'select' && $this->masterTable === null) {
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

    public function exists()
    {
        return (new static())->mode('select')->field(
            new Expression('case when exists[] then 1 else 0 end', [$this])
        );
    }
}
