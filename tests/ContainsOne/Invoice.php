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
        parent:: doInitialize();

        $this->title_field = $this->fieldName()->ref_no;

        $this->addField($this->fieldName()->ref_no, ['required' => true]);

        // will contain one Address
        $this->containsOne($this->fieldName()->addr, ['model' => [Address::class]]);
    }
}
