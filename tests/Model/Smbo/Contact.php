<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Model\Smbo;

use Phlex\Data\Model;

class Contact extends Model
{
    public $table = 'contact';

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->addField('type', ['type' => ['enum', 'values' => ['client', 'supplier']]]);

        $this->addField('name');
    }
}
