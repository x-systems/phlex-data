<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Model;

use Phlex\Data\Model;

class Payment extends Model
{
    public $table = 'payment';

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->hasOne('client', ['theirModel' => [Client::class]]);
        $this->addField('name');
        $this->addField('amount', ['type' => 'money']);
    }
}
