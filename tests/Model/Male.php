<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Model;

class Male extends Person
{
    protected function doInitialize(): void
    {
        parent::doInitialize();
        $this->addCondition('gender', 'M');
    }
}
