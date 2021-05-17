<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\ContainsMany;

use Phlex\Data\Model;

/**
 * VAT rate model.
 *
 * @property string $name @Phlex\Field()
 * @property int    $rate @Phlex\Field()
 */
class VatRate extends Model
{
    public $table = 'vat_rate';

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->addField($this->fieldName()->name);
        $this->addField($this->fieldName()->rate, ['type' => 'integer']);
    }
}
