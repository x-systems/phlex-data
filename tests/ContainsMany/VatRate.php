<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\ContainsMany;

use Phlex\Data\Model;

/**
 * VAT rate model.
 *
 * @property string $name @Atk\Field()
 * @property int    $rate @Atk\Field()
 */
class VatRate extends Model
{
    public $table = 'vat_rate';

    protected function init(): void
    {
        parent::init();

        $this->addField($this->fieldName()->name);
        $this->addField($this->fieldName()->rate, ['type' => 'integer']);
    }
}
