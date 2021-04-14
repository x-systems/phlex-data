<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\ContainsOne;

use Phlex\Data\Model;

/**
 * Country model.
 *
 * @property string $name @Atk\Field()
 */
class Country extends Model
{
    public $table = 'country';

    protected function init(): void
    {
        parent::init();

        $this->addField($this->fieldName()->name);
    }
}
