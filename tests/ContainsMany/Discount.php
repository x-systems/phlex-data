<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\ContainsMany;

use Phlex\Data\Model;

/**
 * Each line can have multiple discounts.
 *
 * @property int       $percent    @Atk\Field()
 * @property \DateTime $valid_till @Atk\Field()
 */
class Discount extends Model
{
    protected function init(): void
    {
        parent::init();

        $this->addField($this->fieldName()->percent, ['type' => 'integer', 'required' => true]);
        $this->addField($this->fieldName()->valid_till, ['type' => 'datetime']);
    }
}
