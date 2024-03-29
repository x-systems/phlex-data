<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Phlex\Data\Model;
use Phlex\Data\Persistence\Sql\Expression;

/**
 * Tests that condition is applied when traversing hasMany
 * also that the original model can be re-loaded with a different
 * value without making any condition stick.
 */
class ReferenceSqlTest extends Sql\TestCase
{
    public function testBasic(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Peter'],
                3 => ['id' => 3, 'name' => 'Joe'],
            ], 'order' => [
                ['amount' => '20', 'user_id' => 1],
                ['amount' => '15', 'user_id' => 2],
                ['amount' => '5', 'user_id' => 1],
                ['amount' => '3', 'user_id' => 1],
                ['amount' => '8', 'user_id' => 3],
            ],
        ]);

        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name']);
        $o = (new Model($this->db, ['table' => 'order']))->addFields(['amount', 'user_id']);

        $u->withMany('Orders', ['theirModel' => $o]);

        $oo = $u->load(1)->ref('Orders');
        $ooo = $oo->tryLoad(1);
        $this->assertEquals(20, $ooo->get('amount'));
        $ooo = $oo->tryLoad(2);
        $this->assertNull($ooo->get('amount'));
        $ooo = $oo->tryLoad(3);
        $this->assertEquals(5, $ooo->get('amount'));

        $oo = $u->load(2)->ref('Orders');
        $ooo = $oo->tryLoad(1);
        $this->assertNull($ooo->get('amount'));
        $ooo = $oo->tryLoad(2);
        $this->assertEquals(15, $ooo->get('amount'));
        $ooo = $oo->tryLoad(3);
        $this->assertNull($ooo->get('amount'));

        $oo = $u->addCondition('id', '>', '1')->ref('Orders');

        $this->assertSameSql(
            'select "id","amount","user_id" from "order" where "user_id" in (select "id" from "user" where "id" > :a)',
            $oo->toQuery()->select()->render()
        );
    }

    /**
     * Tests to make sure refLink properly generates field links.
     */
    public function testLink(): void
    {
        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name']);
        $o = (new Model($this->db, ['table' => 'order']))->addFields(['amount', 'user_id']);

        $u->withMany('Orders', ['theirModel' => $o]);

        $this->assertSameSql(
            'select "id","amount","user_id" from "order" where "user_id" = "user"."id"',
            $u->refLink('Orders')->toQuery()->select()->render()
        );
    }

    public function testBasic2(): void
    {
        $this->setDb([
            'user' => [
                ['name' => 'John', 'currency' => 'EUR'],
                ['name' => 'Peter', 'currency' => 'GBP'],
                ['name' => 'Joe', 'currency' => 'EUR'],
            ], 'currency' => [
                ['currency' => 'EUR', 'name' => 'Euro'],
                ['currency' => 'USD', 'name' => 'Dollar'],
                ['currency' => 'GBP', 'name' => 'Pound'],
            ],
        ]);

        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name', 'currency']);
        $c = (new Model($this->db, ['table' => 'currency']))->addFields(['currency', 'name']);

        $u->withMany('cur', ['theirModel' => $c, 'ourKey' => 'currency', 'theirKey' => 'currency']);

        $cc = $u->load(1)->ref('cur');
        $cc = $cc->tryLoadAny();
        $this->assertSame('Euro', $cc->get('name'));

        $cc = $u->load(2)->ref('cur');
        $cc = $cc->tryLoadAny();
        $this->assertSame('Pound', $cc->get('name'));
    }

    public function testHasMany(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'reference_list' => '++order/1++order/3++order/4++'],
                2 => ['id' => 2, 'name' => 'Peter', 'reference_list' => '++order/2++'],
                3 => ['id' => 3, 'name' => 'Joe', 'reference_list' => '++order/5++'],
            ], 'order' => [
                ['amount' => '20'],
                ['amount' => '15'],
                ['amount' => '5'],
                ['amount' => '3'],
                ['amount' => '8'],
            ],
            'offer' => [
                ['amount' => '20'],
                ['amount' => '15'],
                ['amount' => '5'],
                ['amount' => '3'],
                ['amount' => '8'],
            ],
        ]);

        $user = (new Model($this->db, ['table' => 'user']))->addFields(['name']);

        $reference = (new Model\Union($this->db))->addNestedModels([
            'order' => (new Model($this->db, ['table' => 'order']))->addFields(['amount']),
            'offer' => (new Model($this->db, ['table' => 'offer']))->addFields(['amount']),
        ]);

        $user->hasMany('reference', ['theirModel' => $reference]);

        $oo = $user->load(1)->ref('reference');
        $ooo = $oo->tryLoadAny();
        $this->assertEquals(20, $ooo->get('amount'));
        $ooo = $oo->tryLoad('order/2');
        $this->assertNull($ooo->get('amount'));
        $ooo = $oo->tryLoad('order/3');
        $this->assertEquals(5, $ooo->get('amount'));

        $reference->addField('amount');

        $oo = $user->load(2)->ref('reference');
        $ooo = $oo->tryLoad('order/1');
        $this->assertNull($ooo->get('amount'));
        $ooo = $oo->tryLoad('order/2');
        $this->assertEquals(15, $ooo->get('amount'));
        $ooo = $oo->tryLoad('order/3');
        $this->assertNull($ooo->get('amount'));

        $oo = $user->addCondition('id', '>', '2')->ref('reference');

        if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->assertSameSql(
                'select "_tu"."union_id","_tu"."union_amount" from (select (:a || "id") "union_id","amount" "union_amount" from "order" union all select (:b || "id") "union_id","amount" "union_amount" from "offer") "_tu" where (select exists (select * from "user" where ("id" > :c and ("reference_list" like :d || "_tu"."union_id" || :e))))',
                $oo->toQuery()->select()->render()
            );
        }

        $this->assertSame([['id' => 'order/5']], $oo->export(['id']));
    }

    public function testLink2(): void
    {
        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name', 'currency_code']);
        $c = (new Model($this->db, ['table' => 'currency']))->addFields(['code', 'name']);

        $u->withMany('cur', ['theirModel' => $c, 'ourKey' => 'currency_code', 'theirKey' => 'code']);

        $this->assertSameSql(
            'select "id","code","name" from "currency" where "code" = "user"."currency_code"',
            $u->refLink('cur')->toQuery()->select()->render()
        );
    }

    /**
     * Tests that condition defined on the parent model is retained when traversing
     * through hasMany.
     */
    public function testBasicOne(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Peter'],
                3 => ['id' => 3, 'name' => 'Joe'],
            ], 'order' => [
                ['amount' => '20', 'user_id' => 1],
                ['amount' => '15', 'user_id' => 2],
                ['amount' => '5', 'user_id' => 1],
                ['amount' => '3', 'user_id' => 1],
                ['amount' => '8', 'user_id' => 3],
            ],
        ]);

        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name']);
        $o = (new Model($this->db, ['table' => 'order']))->addFields(['amount']);

        $o->hasOne('user', ['theirModel' => $u]);

        $this->assertSame('John', $o->load(1)->ref('user')->get('name'));
        $this->assertSame('Peter', $o->load(2)->ref('user')->get('name'));
        $this->assertSame('John', $o->load(3)->ref('user')->get('name'));
        $this->assertSame('Joe', $o->load(5)->ref('user')->get('name'));

        $o->addCondition('amount', '>', 6);
        $o->addCondition('amount', '<', 9);

        $this->assertSameSql(
            'select "id","name" from "user" where "id" in (select "user_id" from "order" where ("amount" > :a and "amount" < :b))',
            $o->ref('user')->toQuery()->select()->render()
        );

        $o->addCondition('user', 1);

        $this->assertSameSql(
            'select "id","amount","user_id" from "order" where ("amount" > :a and "amount" < :b and "user_id" = :c)',
            $o->toQuery()->select()->render()
        );
    }

    /**
     * Tests Join::addField's ability to create expressions from foreign fields.
     */
    public function testAddOneField(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'date' => '2001-01-02'],
                2 => ['id' => 2, 'name' => 'Peter', 'date' => '2004-08-20'],
                3 => ['id' => 3, 'name' => 'Joe', 'date' => '2005-08-20'],
            ], 'order' => [
                ['amount' => '20', 'user_id' => 1],
                ['amount' => '15', 'user_id' => 2],
                ['amount' => '5', 'user_id' => 1],
                ['amount' => '3', 'user_id' => 1],
                ['amount' => '8', 'user_id' => 3],
            ],
        ]);

        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name', ['date', 'type' => 'date']]);

        $o = (new Model($this->db, ['table' => 'order']))->addFields(['amount']);
        $o->hasOne('user', ['theirModel' => $u])->addFields(['username' => 'name', ['date', 'type' => 'date']]);

        $this->assertSame('John', $o->load(1)->get('username'));
        $this->assertEquals(new \DateTime('2001-01-02'), $o->load(1)->get('date'));

        $this->assertSame('Peter', $o->load(2)->get('username'));
        $this->assertSame('John', $o->load(3)->get('username'));
        $this->assertSame('Joe', $o->load(5)->get('username'));

        // few more tests
        $o = (new Model($this->db, ['table' => 'order']))->addFields(['amount']);
        $o->hasOne('user', ['theirModel' => $u])->addFields(['username' => 'name', 'thedate' => ['date', 'type' => 'date']]);
        $this->assertSame('John', $o->load(1)->get('username'));
        $this->assertEquals(new \DateTime('2001-01-02'), $o->load(1)->get('thedate'));

        $o = (new Model($this->db, ['table' => 'order']))->addFields(['amount']);
        $o->hasOne('user', ['theirModel' => $u])->addFields(['date'], ['type' => 'date']);
        $this->assertEquals(new \DateTime('2001-01-02'), $o->load(1)->get('date'));
    }

    public function testRelatedExpression(): void
    {
        $vat = 0.23;

        $this->setDb([
            'invoice' => [
                1 => ['id' => 1, 'ref_no' => 'INV203'],
                2 => ['id' => 2, 'ref_no' => 'INV204'],
                3 => ['id' => 3, 'ref_no' => 'INV205'],
            ], 'invoice_line' => [
                ['total_net' => ($n = 10), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 1],
                ['total_net' => ($n = 30), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 1],
                ['total_net' => ($n = 100), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 2],
                ['total_net' => ($n = 25), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 3],
                ['total_net' => ($n = 25), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 3],
            ],
        ]);

        $i = (new Model($this->db, ['table' => 'invoice']))->addFields(['ref_no']);
        $l = (new Model($this->db, ['table' => 'invoice_line']))->addFields(['invoice_id', 'total_net', 'total_vat', 'total_gross']);
        $i->withMany('line', ['theirModel' => $l]);

        $i->addExpression('total_net', $i->refLink('line')->toQuery()->aggregate('sum', 'total_net'));

        $this->assertSameSql(
            'select "invoice"."id","invoice"."ref_no",(select sum("total_net") from "invoice_line" where "invoice_id" = "invoice"."id") "total_net" from "invoice"',
            $i->toQuery()->select()->render()
        );
    }

    public function testAggregateHasMany(): void
    {
        $vat = 0.23;

        $this->setDb([
            'invoice' => [
                1 => ['id' => 1, 'ref_no' => 'INV203'],
                2 => ['id' => 2, 'ref_no' => 'INV204'],
                3 => ['id' => 3, 'ref_no' => 'INV205'],
            ], 'invoice_line' => [
                ['total_net' => ($n = 10), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 1],
                ['total_net' => ($n = 30), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 1],
                ['total_net' => ($n = 100), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 2],
                ['total_net' => ($n = 25), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 3],
                ['total_net' => ($n = 25), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 3],
            ],
        ]);

        $i = (new Model($this->db, ['table' => 'invoice']))->addFields(['ref_no']);
        $l = (new Model($this->db, ['table' => 'invoice_line']))->addFields([
            'invoice_id',
            ['total_net', 'type' => 'money'],
            ['total_vat', 'type' => 'money'],
            ['total_gross', 'type' => 'money'],
        ]);
        $i->withMany('line', ['theirModel' => $l])
            ->addFields([
                ['total_vat', 'aggregate' => 'sum', 'type' => 'money'],
                ['total_net', 'aggregate' => 'sum'],
                ['total_gross', 'aggregate' => 'sum'],
            ]);
        $i = $i->load('1');

        // type was set explicitly
        $this->assertSame(Model\Field\Type\Money::class, get_class($i->getField('total_vat')->getValueType()));

        // type was not set and is not inherited
        $this->assertSame(Model\Field\Type\Generic::class, get_class($i->getField('total_net')->getValueType()));

        $this->assertEquals(40, $i->get('total_net'));
        $this->assertEquals(9.2, $i->get('total_vat'));
        $this->assertEquals(49.2, $i->get('total_gross'));

        $i->ref('line')->import([
            ['total_net' => ($n = 1), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1))],
            ['total_net' => ($n = 2), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1))],
        ]);
        $i->reload();

        $this->assertEquals($n = 43, $i->get('total_net'));
        $this->assertEquals($n * $vat, $i->get('total_vat'));
        $this->assertEquals($n * ($vat + 1), $i->get('total_gross'));

        $i->ref('line')->import([
            ['total_net' => null, 'total_vat' => null, 'total_gross' => 1],
        ]);
        $i->reload();

        $this->assertEquals($n = 43, $i->get('total_net'));
        $this->assertEquals($n * $vat, $i->get('total_vat'));
        $this->assertEquals($n * ($vat + 1) + 1, $i->get('total_gross'));
    }

    public function testOtherAggregates(): void
    {
        if ($this->getDatabasePlatform() instanceof PostgreSQL94Platform) {
            $this->markTestIncomplete('PostgreSQL does not support "SUM(variable)" syntax');
        } elseif ($this->getDatabasePlatform() instanceof SQLServer2012Platform) {
            $this->markTestIncomplete('MSSQL does not support "LENGTH(variable)" function');
        }

        $vat = 0.23;

        $this->setDb([
            'list' => [
                1 => ['id' => 1, 'name' => 'Meat'],
                2 => ['id' => 2, 'name' => 'Veg'],
                3 => ['id' => 3, 'name' => 'Fruit'],
            ], 'item' => [
                ['name' => 'Apple', 'code' => 'ABC', 'list_id' => 3],
                ['name' => 'Banana', 'code' => 'DEF', 'list_id' => 3],
                ['name' => 'Pork', 'code' => 'GHI', 'list_id' => 1],
                ['name' => 'Chicken', 'code' => null, 'list_id' => 1],
                ['name' => 'Pear', 'code' => null, 'list_id' => 3],
            ],
        ]);

        $l = (new Model($this->db, ['table' => 'list']))->addFields(['name']);
        $i = (new Model($this->db, ['table' => 'item']))->addFields(['list_id', 'name', 'code']);
        $l->withMany('Items', ['theirModel' => $i])
            ->addFields([
                ['items_name', 'aggregate' => 'count', 'field' => 'name'],
                ['items_code', 'aggregate' => 'count', 'field' => 'code'], // counts only not-null values
                ['items_star', 'aggregate' => 'count'], // no field set, counts all rows with count(*)
                ['items_c:', 'concat' => '::', 'field' => 'name'],
                ['items_c-', 'aggregate' => new Expression\GroupConcat($i->expr('[name]'), '-')], // @phpstan-ignore-line
                ['len', 'aggregate' => $i->expr('sum(length([name]))')],
                ['len2', 'expr' => 'sum(length([name]))'],
                ['chicken5', 'expr' => 'sum([])', 'args' => ['5']],
            ]);

        $ll = $l->load(1);
        $this->assertEquals(2, $ll->get('items_name')); // 2 not-null values
        $this->assertEquals(1, $ll->get('items_code')); // only 1 not-null value
        $this->assertEquals(2, $ll->get('items_star')); // 2 rows in total
        $this->assertSame($ll->get('items_c:') === 'Pork::Chicken' ? 'Pork::Chicken' : 'Chicken::Pork', $ll->get('items_c:'));
        $this->assertSame($ll->get('items_c-') === 'Pork-Chicken' ? 'Pork-Chicken' : 'Chicken-Pork', $ll->get('items_c-'));
        $this->assertEquals(strlen('Chicken') + strlen('Pork'), $ll->get('len'));
        $this->assertEquals(strlen('Chicken') + strlen('Pork'), $ll->get('len2'));
        $this->assertEquals(10, $ll->get('chicken5'));

        $ll = $l->load(2);
        $this->assertEquals(0, $ll->get('items_name'));
        $this->assertEquals(0, $ll->get('items_code'));
        $this->assertEquals(0, $ll->get('items_star'));
        $this->assertEquals('', $ll->get('items_c:'));
        $this->assertEquals('', $ll->get('items_c-'));
        $this->assertNull($ll->get('len'));
        $this->assertNull($ll->get('len2'));
        $this->assertNull($ll->get('chicken5'));
    }

    public function testReferenceHasOneTraversing(): void
    {
        $this->setDb([
            'user' => [
                ['name' => 'Vinny', 'company_id' => 1],
                ['name' => 'Zoe', 'company_id' => 2],
            ],
            'company' => [
                ['name' => 'Vinny Company'],
                ['name' => 'Zoe Company'],
            ],
            'order' => [
                ['company_id' => 1, 'description' => 'Vinny Company Order 1', 'amount' => 50.0],
                ['company_id' => 2, 'description' => 'Zoe Company Order', 'amount' => 10.0],
                ['company_id' => 1, 'description' => 'Vinny Company Order 2', 'amount' => 15.0],
            ],
        ]);

        $user = (new Model($this->db, ['table' => 'user']))->addFields(['name', 'company_id']);

        $company = (new Model($this->db, ['table' => 'company']))->addFields(['name']);

        $user->hasOne('Company', ['theirModel' => $company, 'ourKey' => 'company_id', 'theirKey' => 'id']);

        $order = new Model($this->db, ['table' => 'order']);
        $order->addField('company_id');
        $order->addField('description');
        $order->addField('amount', ['default' => 20, 'type' => 'float']);

        $company->withMany('Orders', ['theirModel' => $order]);

        $user = $user->load(1);

        $firstUserOrders = $user->ref('Company')->ref('Orders');
        $firstUserOrders->setOrder('id');

        $this->assertEquals([
            ['id' => '1', 'company_id' => 1, 'description' => 'Vinny Company Order 1', 'amount' => 50.0],
            ['id' => '3', 'company_id' => 1, 'description' => 'Vinny Company Order 2', 'amount' => 15.0],
        ], $firstUserOrders->export());

        $user->unload();

        $this->assertEquals([
            ['id' => '1', 'company_id' => 1, 'description' => 'Vinny Company Order 1', 'amount' => 50.0],
            ['id' => '3', 'company_id' => 1, 'description' => 'Vinny Company Order 2', 'amount' => 15.0],
        ], $firstUserOrders->export());

        $this->assertEquals([
            ['id' => '1', 'company_id' => 1, 'description' => 'Vinny Company Order 1', 'amount' => 50.0],
            ['id' => '2', 'company_id' => 2, 'description' => 'Zoe Company Order', 'amount' => 10.0],
            ['id' => '3', 'company_id' => 1, 'description' => 'Vinny Company Order 2', 'amount' => 15.0],
        ], $user->ref('Company')->ref('Orders')->setOrder('id')->export());
    }

    public function testReferenceHook(): void
    {
        $this->setDb([
            'user' => [
                ['name' => 'John', 'contact_id' => 2],
                ['name' => 'Peter', 'contact_id' => null],
                ['name' => 'Joe', 'contact_id' => 3],
            ], 'contact' => [
                ['address' => 'Sue contact'],
                ['address' => 'John contact'],
                ['address' => 'Joe contact'],
            ],
        ]);

        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name']);
        $c = (new Model($this->db, ['table' => 'contact']))->addFields(['address']);

        $u->hasOne('contact', ['theirModel' => $c])
            ->addField('address');

        $uu = $u->load(1);
        $this->assertSame('John contact', $uu->get('address'));
        $this->assertSame('John contact', $uu->ref('contact')->get('address'));

        $uu = $u->load(2);
        $this->assertNull($uu->get('address'));
        $this->assertNull($uu->get('contact_id'));
        $this->assertNull($uu->ref('contact')->get('address'));

        $uu = $u->load(3);
        $this->assertSame('Joe contact', $uu->get('address'));
        $this->assertSame('Joe contact', $uu->ref('contact')->get('address'));

        $uu = $u->load(2);
        $uu->ref('contact')->save(['address' => 'Peters new contact']);

        $this->assertNotNull($uu->get('contact_id'));
        $this->assertSame('Peters new contact', $uu->ref('contact')->get('address'));

        $uu->save()->reload();
        $this->assertSame('Peters new contact', $uu->ref('contact')->get('address'));
        $this->assertSame('Peters new contact', $uu->get('address'));
    }

    /**
     * test case hasOne::our_key == owner::primaryKey.
     */
    public function testIdFieldReferenceOurFieldCase(): void
    {
        $this->setDb([
            'player' => [
                ['name' => 'John'],
                ['name' => 'Messi'],
                ['name' => 'Ronaldo'],
            ],
            'stadium' => [
                ['name' => 'Sue bernabeu', 'player_id' => 3],
                ['name' => 'John camp', 'player_id' => 1],
            ],
        ]);

        $p = (new Model($this->db, ['table' => 'player']))->addFields(['name']);

        $s = (new Model($this->db, ['table' => 'stadium']));
        $s->addFields(['name']);
        $s->hasOne('player', ['theirModel' => $p]);

        $p->hasOne('stadium', ['theirModel' => $s, 'ourKey' => 'id', 'theirKey' => 'player_id']);

        $p = $p->load(2);
        $p->ref('stadium')->import([['name' => 'Nou camp nou']]);
        $this->assertSame('Nou camp nou', $p->ref('stadium')->get('name'));
        $this->assertSame(2, $p->ref('stadium')->get('player_id'));
    }

    public function testModelProperty(): void
    {
        $user = new Model($this->db, ['table' => 'user']);
        $user->withMany('Orders', ['theirModel' => [Model::class, 'table' => 'order'], 'theirKey' => 'id']);
        $o = $user->ref('Orders');
        $this->assertSame('order', $o->table);
    }

    /**
     * Few tests to test Reference\Sql\HasOne addTitle() method.
     */
    public function testAddTitle(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
            ], 'order' => [
                ['amount' => '20', 'user_id' => 1],
                ['amount' => '15', 'user_id' => 2],
            ],
        ]);

        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name']);
        $o = (new Model($this->db, ['table' => 'order']))->addFields(['amount']);

        // by default not set
        $o->hasOne('user', ['theirModel' => $u]);
        $this->assertSame($o->getField('user')->isVisible(), true);

        $o->getReference('user')->addTitle();
        $this->assertTrue($o->hasField('user_name'));
        $this->assertSame($o->getField('user_name')->isVisible(), true);

        // if it is set manually then it will not be changed
        $o = (new Model($this->db, ['table' => 'order']))->addFields(['amount']);
        $o->hasOne('user', ['theirModel' => $u]);
        $o->getField('user_id')->ui['visible'] = true;
        $o->getReference('user')->addTitle();

        $this->assertSame($o->getField('user_id')->isVisible(), true);
    }

    /**
     * Tests that if we change hasOne->addTitle() field value then it will also update
     * link field value when saved.
     */
    public function testHasOneTitleSet(): void
    {
        $dbData = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'last_name' => 'Doe'],
                2 => ['id' => 2, 'name' => 'Peter', 'last_name' => 'Foo'],
                3 => ['id' => 3, 'name' => 'Goofy', 'last_name' => 'Goo'],
            ], 'order' => [
                1 => ['id' => 1, 'user_id' => 1],
                2 => ['id' => 2, 'user_id' => 2],
                3 => ['id' => 3, 'user_id' => 1],
            ],
        ];

        // restore DB
        $this->setDb($dbData);

        // with default titleKey='name'
        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name', 'last_name']);
        $o = (new Model($this->db, ['table' => 'order']));
        $o->hasOne('user', ['theirModel' => $u])->addTitle();

        // change order user by changing titleKey value
        $o = $o->load(1);
        $o->set('user_name', 'Peter');
        $this->assertEquals(1, $o->get('user_id'));
        $o->save();
        $this->assertEquals(2, $o->get('user_id')); // user_id changed to Peters ID
        $o->reload();
        $this->assertEquals(2, $o->get('user_id')); // and it's really saved like that

        // restore DB
        $this->setDb($dbData);

        // with custom titleKey='last_name'
        $u = (new Model($this->db, ['table' => 'user', 'titleKey' => 'last_name']))->addFields(['name', 'last_name']);
        $o = (new Model($this->db, ['table' => 'order']));
        $o->hasOne('user', ['theirModel' => $u])->addTitle();

        // change order user by changing titleKey value
        $o = $o->load(1);
        $o->set('user_name', 'Foo');
        $this->assertEquals(1, $o->get('user_id'));
        $o->save();
        $this->assertEquals(2, $o->get('user_id')); // user_id changed to Peters ID
        $o->reload();
        $this->assertEquals(2, $o->get('user_id')); // and it's really saved like that

        // restore DB
        $this->setDb($dbData);

        // with custom titleKey='last_name' and custom link name
        $u = (new Model($this->db, ['table' => 'user', 'titleKey' => 'last_name']))->addFields(['name', 'last_name']);
        $o = (new Model($this->db, ['table' => 'order']));
        $o->hasOne('my_user', ['theirModel' => $u, 'ourKey' => 'user_id'])->addTitle();

        // change order user by changing ref field value
        $o = $o->load(1);
        $o->set('my_user_name', 'Foo');
        $this->assertEquals(1, $o->get('user_id'));
        $o->save();
        $this->assertEquals(2, $o->get('user_id')); // user_id changed to Peters ID
        $o->reload();
        $this->assertEquals(2, $o->get('user_id')); // and it's really saved like that

        // restore DB
        $this->setDb($dbData);

        // with custom titleKey='last_name' and custom link name
        $u = (new Model($this->db, ['table' => 'user', 'titleKey' => 'last_name']))->addFields(['name', 'last_name']);
        $o = (new Model($this->db, ['table' => 'order']));
        $o->hasOne('my_user', ['theirModel' => $u, 'ourKey' => 'user_id'])->addTitle();

        // change order user by changing ref field value
        $o = $o->load(1);
        $o->set('my_user_name', 'Foo'); // user_id=2
        $o->set('user_id', 3);     // user_id=3 (this will take precedence)
        $this->assertEquals(3, $o->get('user_id'));
        $o->save();
        $this->assertEquals(3, $o->get('user_id')); // user_id changed to Goofy ID
        $o->reload();
        $this->assertEquals(3, $o->get('user_id')); // and it's really saved like that
    }

    /**
     * Tests that if we change hasOne->addTitle() field value then it will also update
     * link field value when saved.
     */
    public function testHasOneReferenceCaption(): void
    {
        // restore DB
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'last_name' => 'Doe'],
                2 => ['id' => 2, 'name' => 'Peter', 'last_name' => 'Foo'],
                3 => ['id' => 3, 'name' => 'Goofy', 'last_name' => 'Goo'],
            ],
            'order' => [
                1 => ['id' => 1, 'user_id' => 1],
                2 => ['id' => 2, 'user_id' => 2],
                3 => ['id' => 3, 'user_id' => 1],
            ],
        ]);
        $u = (new Model($this->db, ['table' => 'user', 'titleKey' => 'last_name']))->addFields(['name', 'last_name']);

        // Test : Now the caption is null and is generated from field name
        $this->assertSame('Last Name', $u->getField('last_name')->getCaption());

        $u->getField('last_name')->caption = 'Surname';

        // Test : Now the caption is not null and the value is returned
        $this->assertSame('Surname', $u->getField('last_name')->getCaption());

        $o = (new Model($this->db, ['table' => 'order']));
        $order_user_ref = $o->hasOne('my_user', ['theirModel' => $u, 'ourKey' => 'user_id']);
        $order_user_ref->addField('user_last_name', 'last_name');

        $referenced_caption = $o->getField('user_last_name')->getCaption();

        // Test : $field->caption for the field 'last_name' is defined in referenced model (User)
        // When Order add field from Referenced model User
        // caption will be passed to Order field user_last_name
        $this->assertSame('Surname', $referenced_caption);
    }
}
