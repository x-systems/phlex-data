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

    protected function _render_group_concat()
    {
        return new Sql\Expression('listagg({field}, [delimiter]) within group (order by {field})', $this->args['custom']);
    }

    public function exists()
    {
        return (new static())->mode('select')->field(
            new Sql\Expression('case when exists[] then 1 else 0 end', [$this])
        );
    }
}
