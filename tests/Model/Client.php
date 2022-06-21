<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Model;

class Client extends User
{
    public $table = 'client';

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->addField('order', ['default' => '10']);
    }
}
