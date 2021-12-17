<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\ContainsOne;

use Phlex\Data\Model;

/**
 * Address model.
 *
 * @property Country   $country_id @Phlex\RefOne()
 * @property string    $address    @Phlex\Field()
 * @property \DateTime $built_date @Phlex\Field()
 * @property string[]  $tags       @Phlex\Field()
 * @property DoorCode  $door_code  @Phlex\RefOne()
 */
class Address extends Model
{
    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->hasOne($this->key()->country_id, ['theirModel' => [Country::class], 'type' => 'integer']);

        $this->addField($this->key()->address);
        $this->addField($this->key()->built_date, ['type' => 'datetime']);
        $this->addField($this->key()->tags, ['type' => 'array', 'default' => []]);

        // will contain one door code
        $this->containsOne($this->key()->door_code, ['theirModel' => [DoorCode::class], 'caption' => 'Secret Code']);
    }
}
