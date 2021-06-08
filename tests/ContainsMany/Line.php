<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\ContainsMany;

use Phlex\Data\Model;

/**
 * Invoice lines model.
 *
 * @property VatRate   $vat_rate_id       @Phlex\RefOne()
 * @property float     $price             @Phlex\Field()
 * @property float     $qty               @Phlex\Field()
 * @property \DateTime $add_date          @Phlex\Field()
 * @property string    $total_gross       @Phlex\Field()
 * @property Discount  $discounts         @Phlex\RefOne()
 * @property float     $discounts_percent @Phlex\Field()
 */
class Line extends Model
{
    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->hasOne($this->key()->vat_rate_id, ['model' => [VatRate::class]]);

        $this->addField($this->key()->price, ['type' => 'money', 'required' => true]);
        $this->addField($this->key()->qty, ['type' => 'float', 'required' => true]);
        $this->addField($this->key()->add_date, ['type' => 'datetime']);

        $this->addExpression($this->key()->total_gross, function (self $m) {
            return $m->price * $m->qty * (1 + $m->vat_rate_id->rate / 100);
        });

        // each line can have multiple discounts and calculate total of these discounts
        $this->containsMany($this->key()->discounts, ['model' => [Discount::class]]);

        $this->addCalculatedField($this->key()->discounts_percent, function ($m) {
            $total = 0;
            foreach ($m->discounts as $d) {
                $total += $d->percent;
            }

            return $total;
        });
    }
}
