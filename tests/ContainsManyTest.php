<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Exception;
use Phlex\Data\Tests\ContainsMany\Invoice;
use Phlex\Data\Tests\ContainsMany\VatRate;

/**
 * Model structure:.
 *
 * Invoice (SQL)
 *   - containsMany(Line)
 *     - hasOne(VatRate, SQL)
 *     - containsMany(Discount)
 */

/**
 * ATK Data has support of containsOne / containsMany.
 * Basically data model can contain other data models with one or many records.
 */
class ContainsManyTest extends Sql\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // populate database for our models
        $this->createMigrator(new VatRate($this->db))->dropIfExists()->create();
        $this->createMigrator(new Invoice($this->db))->dropIfExists()->create();

        // fill in some default values
        $m = new VatRate($this->db);
        $m->import([
            [
                $m->key()->id => 1,
                $m->key()->name => '21% rate',
                $m->key()->rate => 21,
            ],
            [
                $m->key()->id => 2,
                $m->key()->name => '15% rate',
                $m->key()->rate => 15,
            ],
        ]);

        $m = new Invoice($this->db);
        $m->import([
            [
                $m->key()->id => 1,
                $m->key()->ref_no => 'A1',
                $m->key()->amount => 123,
            ],
            [
                $m->key()->id => 2,
                $m->key()->ref_no => 'A2',
                $m->key()->amount => 456,
            ],
        ]);
    }

    /**
     * Test caption of referenced model.
     */
    public function testModelCaption(): void
    {
        $i = new Invoice($this->db);

        // test caption of containsMany reference
        $this->assertSame('My Invoice Lines', $i->getField($i->key()->lines)->getCaption());
        $this->assertSame('My Invoice Lines', $i->refModel($i->key()->lines)->getCaption());
        $this->assertSame('My Invoice Lines', $i->lines->getCaption());
    }

    /**
     * Test containsMany.
     */
    public function testContainsMany(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->key()->ref_no, 'A1');

        // now let's add some lines
        $l = $i->lines;
        $rows = [
            1 => [
                $l->key()->id => 1,
                $l->key()->vat_rate_id => 1,
                $l->key()->price => 10,
                $l->key()->qty => 2,
                $l->key()->discounts_data => null,
                $l->key()->add_date => new \DateTime('2019-01-01'),
            ],
            2 => [
                $l->key()->id => 2,
                $l->key()->vat_rate_id => 2,
                $l->key()->price => 15,
                $l->key()->qty => 5,
                $l->key()->discounts_data => null,
                $l->key()->add_date => new \DateTime('2019-01-01'),
            ],
            3 => [
                $l->key()->id => 3,
                $l->key()->vat_rate_id => 1,
                $l->key()->price => 40,
                $l->key()->qty => 1,
                $l->key()->discounts_data => null,
                $l->key()->add_date => new \DateTime('2019-01-01'),
            ],
        ];

        foreach ($rows as $row) {
            $l->insert($row);
        }

        // reload invoice just in case
        $this->assertEquals($rows, $i->ref('lines')->export());
        $i->reload();
        $this->assertEquals($rows, $i->ref('lines')->export());

        // now let's delete line with id=2 and add one more line
        $i->lines
            ->load(2)->delete()
            ->insert([
                $l->key()->vat_rate_id => 2,
                $l->key()->price => 50,
                $l->key()->qty => 3,
                $l->key()->discounts_data => null,
                $l->key()->add_date => new \DateTime('2019-01-01'),
            ]);
        $rows = [
            1 => [
                $l->key()->id => 1,
                $l->key()->vat_rate_id => 1,
                $l->key()->price => 10,
                $l->key()->qty => 2,
                $l->key()->discounts_data => null,
                $l->key()->add_date => new \DateTime('2019-01-01'),
            ],
            3 => [
                $l->key()->id => 3,
                $l->key()->vat_rate_id => 1,
                $l->key()->price => 40,
                $l->key()->qty => 1,
                $l->key()->discounts_data => null,
                $l->key()->add_date => new \DateTime('2019-01-01'),
            ],
            4 => [
                $l->key()->id => 4,
                $l->key()->vat_rate_id => 2,
                $l->key()->price => 50,
                $l->key()->qty => 3,
                $l->key()->discounts_data => null,
                $l->key()->add_date => new \DateTime('2019-01-01'),
            ],
        ];
        $this->assertEquals($rows, $i->lines->export());

        // try hasOne reference
        $v = $i->lines->load(4)->vat_rate;
        $this->assertSame(15, $v->rate);

        // test expression fields
        $v = $i->lines->load(4);
        $this->assertSame(50 * 3 * (1 + 15 / 100), $v->total_gross);

        // and what about calculated field?
        $i->reload(); // we need to reload invoice for changes in lines to be recalculated
        $this->assertSame(10 * 2 * (1 + 21 / 100) + 40 * 1 * (1 + 21 / 100) + 50 * 3 * (1 + 15 / 100), $i->total_gross); // =245.10
    }

    /**
     * Model should be loaded before traversing to containsMany relation.
     */
    /* Imants: it looks that this is not actually required - disabling
    public function testEx1(): void
    {
        $i = new Invoice($this->db);
        $this->expectException(Exception::class);
        $i->lines;
    }
    */

    /**
     * Nested containsMany tests.
     */
    public function testNestedContainsMany(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->key()->ref_no, 'A1');

        // now let's add some lines
        $l = $i->lines;

        $rows = [
            1 => [
                $l->key()->id => 1,
                $l->key()->vat_rate_id => 1,
                $l->key()->price => 10,
                $l->key()->qty => 2,
                $l->key()->add_date => new \DateTime('2019-06-01'),
            ],
            2 => [
                $l->key()->id => 2,
                $l->key()->vat_rate_id => 2,
                $l->key()->price => 15,
                $l->key()->qty => 5,
                $l->key()->add_date => new \DateTime('2019-07-01'),
            ],
        ];
        foreach ($rows as $row) {
            $l->insert($row);
        }

        // add some discounts
        $l->load(1)->discounts->insert([
            $l->discounts->key()->id => 1,
            $l->discounts->key()->percent => 5,
            $l->discounts->key()->valid_till => new \DateTime('2019-07-15'),
        ]);
        $l->load(1)->discounts->insert([
            $l->discounts->key()->id => 2,
            $l->discounts->key()->percent => 10,
            $l->discounts->key()->valid_till => new \DateTime('2019-07-30'),
        ]);
        $l->load(2)->discounts->insert([
            $l->discounts->key()->id => 1,
            $l->discounts->key()->percent => 20,
            $l->discounts->key()->valid_till => new \DateTime('2019-12-31'),
        ]);

        // reload invoice to be sure all is saved and to recalculate all fields
        $i->reload();

        // ok, so now let's test
        $this->assertEquals([
            1 => [
                $l->discounts->key()->id => 1,
                $l->discounts->key()->percent => 5,
                $l->discounts->key()->valid_till => new \DateTime('2019-07-15'),
            ],
            2 => [
                $l->discounts->key()->id => 2,
                $l->discounts->key()->percent => 10,
                $l->discounts->key()->valid_till => new \DateTime('2019-07-30'),
            ],
        ], $i->lines->load(1)->discounts->export());

        // is total_gross correctly calculated?
        $this->assertSame(10 * 2 * (1 + 21 / 100) + 15 * 5 * (1 + 15 / 100), $i->total_gross); // =110.45

        // do we also correctly calculate discounts from nested containsMany?
        $this->assertSame(24.2 * 15 / 100 + 86.25 * 20 / 100, $i->discounts_total_sum); // =20.88

        // let's test how it all looks in persistence without encoding
        $exp_lines = $i->setOrder($i->key()->id)->export(null, null, false)[0][$i->key()->lines_data];
        $formatDtForCompareFunc = function (\DateTimeInterface $dt): string {
            $dt = (clone $dt)->setTimeZone(new \DateTimeZone('UTC')); // @phpstan-ignore-line

            return $dt->format('Y-m-d H:i:s.u');
        };
        $this->assertSame(
            json_encode([
                1 => [
                    $i->lines->key()->id => 1,
                    $i->lines->key()->vat_rate_id => 1,
                    $i->lines->key()->price => 10,
                    $i->lines->key()->qty => 2,
                    $i->lines->key()->add_date => $formatDtForCompareFunc(new \DateTime('2019-06-01')),
                    $i->lines->key()->discounts_data => json_encode([
                        1 => [
                            $i->lines->discounts->key()->id => 1,
                            $i->lines->discounts->key()->percent => 5,
                            $i->lines->discounts->key()->valid_till => $formatDtForCompareFunc(new \DateTime('2019-07-15')),
                        ],
                        2 => [
                            $i->lines->discounts->key()->id => 2,
                            $i->lines->discounts->key()->percent => 10,
                            $i->lines->discounts->key()->valid_till => $formatDtForCompareFunc(new \DateTime('2019-07-30')),
                        ],
                    ]),
                ],
                2 => [
                    $i->lines->key()->id => 2,
                    $i->lines->key()->vat_rate_id => 2,
                    $i->lines->key()->price => 15,
                    $i->lines->key()->qty => 5,
                    $i->lines->key()->add_date => $formatDtForCompareFunc(new \DateTime('2019-07-01')),
                    $i->lines->key()->discounts_data => json_encode([
                        1 => [
                            $i->lines->discounts->key()->id => 1,
                            $i->lines->discounts->key()->percent => 20,
                            $i->lines->discounts->key()->valid_till => $formatDtForCompareFunc(new \DateTime('2019-12-31')),
                        ],
                    ]),
                ],
            ]),
            $exp_lines
        );
    }
}
