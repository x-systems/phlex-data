<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Model\Smbo;

class Transfer extends Payment
{
    public $detached = false;

    public $other_leg_creation;

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->j_payment->hasOne('transfer_document', ['theirModel' => [self::class]]);

        // only used to create / destroy trasfer legs
        if (!$this->detached) {
            $this->addCondition('transfer_document_id', '!=', null);
        }

        $this->addField('destination_account_id', ['never_persist' => true]);

        $this->onHookShort(self::HOOK_BEFORE_SAVE, function () {
            // only for new records and when destination_account_id is set
            if ($this->get('destination_account_id') && !$this->getId()) {
                // In this section we test if "clone" works ok

                $this->other_leg_creation = $m2 = clone $this;
                $m2->set('account_id', $m2->get('destination_account_id'));
                $m2->set('amount', -$m2->get('amount'));

                $m2->reset('destination_account_id');

                $this->set('transfer_document_id', $m2->saveWithoutReloading()->getId());
            }
        });

        $this->onHookShort(self::HOOK_AFTER_SAVE, function () {
            if ($this->other_leg_creation) {
                $this->other_leg_creation->set('transfer_document_id', $this->getId())->save();
            }
            $this->other_leg_creation = null;
        });
    }
}
