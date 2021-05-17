<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\ContainsMany;

use Phlex\Data\Model;

/**
 * Each line can have multiple discounts.
 *
 * @property int       $percent    @Phlex\Field()
 * @property \DateTime $valid_till @Phlex\Field()
 */
class Discount extends Model
{
    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->addField($this->fieldName()->percent, ['type' => 'integer', 'required' => true]);
        $this->addField($this->fieldName()->valid_till, ['type' => 'datetime']);
    }
}
