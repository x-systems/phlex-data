<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Model;

class Male extends Person
{
    protected function init(): void
    {
        parent::init();
        $this->addCondition('gender', 'M');
    }
}
