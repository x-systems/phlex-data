<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\ContainsMany;

use Phlex\Data\Model;

/**
 * Invoice model.
 *
 * @property string $ref_no              @Phlex\Field()
 * @property float  $amount              @Phlex\Field()
 * @property Line   $lines               @Phlex\RefOne()
 * @property array  $lines_data          @Phlex\Field()
 * @property string $total_gross         @Phlex\Field()
 * @property float  $discounts_total_sum @Phlex\Field()
 */
class Invoice extends Model
{
    public $table = 'invoice';

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->titleKey = $this->key()->ref_no;

        $this->addField($this->key()->ref_no, ['required' => true]);
        $this->addField($this->key()->amount, ['type' => 'money']);

        // will contain many Lines
        $this->containsMany($this->key()->lines, ['theirModel' => [Line::class], 'caption' => 'My Invoice Lines']);

        // total_gross - calculated by php callback not by SQL expression
        $this->addCalculatedField($this->key()->total_gross, function (self $m) {
            $total = 0;
            foreach ($m->lines as $line) {
                $total += $line->total_gross;
            }

            return $total;
        });

        // discounts_total_sum - calculated by php callback not by SQL expression
        $this->addCalculatedField($this->key()->discounts_total_sum, function (self $m) {
            $total = 0;
            foreach ($m->lines as $line) {
                $total += (float) $line->total_gross * (float) $line->discounts_percent / 100;
            }

            return $total;
        });
    }
}
