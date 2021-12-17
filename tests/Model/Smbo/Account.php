<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Model\Smbo;

use Phlex\Data\Model;

class Account extends Model
{
    public $table = 'account';

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->addField('name');

        $this->hasMany('Payment', ['theirModel' => [Payment::class]])
            ->addField('balance', ['aggregate' => 'sum', 'field' => 'amount']);
    }

    /**
     * create and return a trasnfer model.
     */
    public function transfer(self $a, $amount)
    {
        $t = new Transfer($this->persistence, ['detached' => true]);
        $t = $t->createEntity();
        $t->set('account_id', $this->getId());

        $t->set('destination_account_id', $a->getId());

        $t->set('amount', -$amount);

        return $t;
    }
}
