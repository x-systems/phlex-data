<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Tests\ContainsOne\Country;
use Phlex\Data\Tests\ContainsOne\Invoice;

/**
 * Model structure:.
 *
 * Invoice (SQL)
 *   - containsOne(Address)
 *     - hasOne(Country, SQL)
 *     - containsOne(DoorCode)
 */

/**
 * ATK Data has support of containsOne / containsMany.
 * Basically data model can contain other data models with one or many records.
 */
class ContainsOneTest extends Sql\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // populate database for our models
        $this->createMigrator(new Country($this->db))->dropIfExists()->create();
        $this->createMigrator(new Invoice($this->db))->dropIfExists()->create();

        // fill in some default values
        $m = new Country($this->db);
        $m->import([
            [
                $m->key()->id => 1,
                $m->key()->name => 'Latvia',
            ],
            [
                $m->key()->id => 2,
                $m->key()->name => 'United Kingdom',
            ],
        ]);

        $m = new Invoice($this->db);
        $m->import([
            [
                $m->key()->id => 1,
                $m->key()->ref_no => 'A1',
                $m->key()->addr => null,
            ],
            [
                $m->key()->id => 2,
                $m->key()->ref_no => 'A2',
                $m->key()->addr => null,
            ],
        ]);
    }

    /**
     * Test caption of referenced model.
     */
    public function testModelCaption(): void
    {
        $a = (new Invoice($this->db))->addr;

        // test caption of containsOne reference
        $this->assertSame('Secret Code', $a->getField($a->key()->door_code)->getCaption());
        $this->assertSame('Secret Code', $a->refModel($a->key()->door_code)->getCaption());
        $this->assertSame('Secret Code', $a->door_code->getCaption());
    }

    /**
     * Test containsOne.
     */
    public function testContainsOne(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->key()->ref_no, 'A1');

        // check do we have address set
        $a = $i->addr->tryLoadAny();
        $this->assertFalse($a->isLoaded());

        // now store some address
        $a->setMulti($row = [
            $a->key()->country_id => 1,
            $a->key()->address => 'foo',
            $a->key()->built_date => new \DateTime('2019-01-01'),
            $a->key()->tags => ['foo', 'bar'],
            $a->key()->door_code => null,
        ]);
        $a->save();

        // now reload invoice and see if it is saved
        $this->assertEquals($row, array_intersect_key($i->addr->get(), $row));
        $i->reload();
        $this->assertEquals($row, array_intersect_key($i->addr->get(), $row));

        // now try to change some field in address
        $i->ref('addr')->set($i->addr->key()->address, 'bar')->save();
        $this->assertSame('bar', $i->addr->address);

        // now add nested containsOne - DoorCode
        $c = $i->addr->door_code;
        $c->setMulti($row = [
            $c->key()->code => 'ABC',
            $c->key()->valid_till => new \DateTime('2019-07-01'),
        ]);
        $c->save();
        $this->assertEquals($row, array_intersect_key($i->addr->door_code->get(), $row));

        // update DoorCode
        $i->reload();
        $i->addr->door_code->save([$i->addr->door_code->key()->code => 'DEF']);
        $this->assertEquals(array_merge($row, [$i->addr->door_code->key()->code => 'DEF']), array_intersect_key($i->addr->door_code->get(), $row));

        // try hasOne reference
        $this->assertSame('Latvia', $i->addr->country->name);
        $i->addr->set($i->addr->key()->country_id, 2)->save();
        $this->assertSame('United Kingdom', $i->addr->country->name);

        // let's test how it all looks in persistence without encoding
        $exp_addr = $i->setOrder('id')->export(null, null, false)[0][$i->key()->addr];
        $formatDtForCompareFunc = function (\DateTimeInterface $dt): string {
            $dt = (clone $dt)->setTimeZone(new \DateTimeZone('UTC')); // @phpstan-ignore-line

            return $dt->format('Y-m-d H:i:s.u');
        };
        $this->assertSame(
            json_encode([
                $i->addr->key()->id => 1,
                $i->addr->key()->country_id => 2,
                $i->addr->key()->address => 'bar',
                $i->addr->key()->built_date => $formatDtForCompareFunc(new \DateTime('2019-01-01')),
                $i->addr->key()->tags => json_encode(['foo', 'bar']),
                $i->addr->key()->door_code => json_encode([
                    $i->addr->door_code->key()->id => 1,
                    $i->addr->door_code->key()->code => 'DEF',
                    $i->addr->door_code->key()->valid_till => $formatDtForCompareFunc(new \DateTime('2019-07-01')),
                ]),
            ]),
            $exp_addr
        );

        // so far so good. now let's try to delete door_code
        $i->addr->door_code->delete();
        $this->assertNull($i->addr->get($i->addr->key()->door_code));
        $this->assertFalse($i->addr->door_code->isLoaded());

        // and now delete address
        $i->addr->delete();
        $this->assertNull($i->get($i->key()->addr));
        $this->assertFalse($i->addr->isLoaded());

        //var_dump($i->export(), $i->export(null, null, false));
    }

    /**
     * How containsOne performs when not all values are stored or there are more values in DB than fields in model.
     */
    public function testContainsOneWhenChangeModelFields(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->key()->ref_no, 'A1');

        // with address
        $a = $i->addr;
        $a->setMulti($row = [
            $a->key()->country_id => 1,
            $a->key()->address => 'foo',
            $a->key()->built_date => new \DateTime('2019-01-01'),
            $a->key()->tags => [],
            $a->key()->door_code => null,
        ]);
        $a->save();

        // now let's add one more field in address model and save
        $a->addField('post_index');
        $a->set('post_index', 'LV-1234');
        $a->save();

        $rowWithField = array_merge($row, ['post_index' => 'LV-1234']);

        $this->assertEquals($rowWithField, array_intersect_key($a->get(), $rowWithField));

        // now this one is a bit tricky
        // each time you call ref() it returns you new model object so it will not have post_index field
        $this->assertFalse($i->addr->hasField('post_index'));

        // now reload invoice just in case
        $i->reload();

        // and it references to same old Address model without post_index field - no errors
        $a = $i->addr;
        $this->assertEquals($row, array_intersect_key($a->get(), $row));
    }

    /*
     * Model should be loaded before traversing to containsOne relation.
     * Imants: it looks that this is not actually required - disabling.
     */
    /*
    public function testEx1(): void
    {
        $i = new Invoice($this->db);
        $this->expectException(Exception::class);
        $i->addr;
    }
    */
}
