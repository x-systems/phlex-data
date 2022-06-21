<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Model;

use Phlex\Data\Model\Union;
use Phlex\Data\Persistence;

class Transaction extends Union
{
    /** @var bool */
    public $subtractInvoice;

    protected function doInitialize(): void
    {
        parent::doInitialize();

        // first lets define nested models
        $this->addNestedModels([
            'invoice' => new Invoice(),
            'payment' => new Payment(),
        ]);

        // next, define common fields
        $this->hasOne('client', ['theirModel' => [Client::class]]);
        $this->addField('name');

        $this->addField('amount', [
            'type' => 'money',
            'actual' => $this->subtractInvoice ? [
                'invoice' => [Persistence\Sql\Field\Expression::class, 'expr' => 'CAST(-[amount] as float)'],
            ] : null,
        ]);
    }
}
