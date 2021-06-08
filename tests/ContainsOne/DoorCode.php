<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\ContainsOne;

use Phlex\Data\Model;

/**
 * DoorCode model.
 *
 * @property string    $code       @Phlex\Field()
 * @property \DateTime $valid_till @Phlex\Field()
 */
class DoorCode extends Model
{
    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->addField($this->key()->code);
        $this->addField($this->key()->valid_till, ['type' => 'datetime']);
    }
}
