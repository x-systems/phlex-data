<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\ContainsOne;

use Phlex\Data\Model;

/**
 * Address model.
 *
 * @property Country   $country_id @Atk\RefOne()
 * @property string    $address    @Atk\Field()
 * @property \DateTime $built_date @Atk\Field()
 * @property string[]  $tags       @Atk\Field()
 * @property DoorCode  $door_code  @Atk\RefOne()
 */
class Address extends Model
{
    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->hasOne($this->fieldName()->country_id, ['model' => [Country::class], 'type' => 'integer']);

        $this->addField($this->fieldName()->address);
        $this->addField($this->fieldName()->built_date, ['type' => 'datetime']);
        $this->addField($this->fieldName()->tags, ['type' => 'array', 'default' => []]);

        // will contain one door code
        $this->containsOne($this->fieldName()->door_code, ['model' => [DoorCode::class], 'caption' => 'Secret Code']);
    }
}
