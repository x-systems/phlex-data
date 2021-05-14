<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Model\Smbo;

use Phlex\Data\Model;

class Payment extends Document
{
    /** @var Model\Join */
    public $j_payment;

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->addCondition('doc_type', 'payment');

        $this->j_payment = $this->join('payment.document_id');

        $this->j_payment->addField('cheque_no');
        $this->j_payment->hasOne('account_id', ['model' => [Account::class]]);

        $this->j_payment->addField('misc_payment', ['type' => 'bool']);
    }
}
