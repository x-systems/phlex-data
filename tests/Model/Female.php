<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Model;

class Female extends Person
{
    protected function doInitialize(): void
    {
        parent::doInitialize();
        $this->addCondition('gender', 'F');
    }
}
