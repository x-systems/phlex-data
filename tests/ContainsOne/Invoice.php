<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\ContainsOne;

use Phlex\Data\Model;

/**
 * Invoice model.
 *
 * @property string  $ref_no @Phlex\Field()
 * @property Address $addr   @Phlex\RefOne()
 */
class Invoice extends Model
{
    public $table = 'invoice';

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->titleKey = $this->key()->ref_no;

        $this->addField($this->key()->ref_no, ['required' => true]);

        // will contain one Address
        $this->containsOne($this->key()->addr, ['model' => [Address::class]]);
    }
}
