<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Core\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

class ReferenceTest extends \Phlex\Core\PHPUnit\TestCase
{
    public function testBasicReferences()
    {
        $user = new Model(null, ['table' => 'user']);
        $user->addField('id');
        $user->addField('name');
        $user = $user->createEntity();
        $user->setId(1)->save();

        $order = new Model();
        $order->addField('id');
        $order->addField('amount', ['default' => 20]);
        $order->addField('user_id');

        $user->withMany('Orders', ['theirModel' => $order, 'caption' => 'My Orders']);
        $o = $user->ref('Orders')->createEntity();

        $this->assertSame(20, $o->get('amount'));
        $this->assertSame(1, $o->get('user_id'));

        $user->withMany('BigOrders', ['theirModel' => function () {
            $m = new Model();
            $m->addField('amount', ['default' => 100]);
            $m->addField('user_id');

            return $m;
        }]);

        $this->assertSame(100, $user->ref('BigOrders')->createEntity()->get('amount'));
    }

    /**
     * Test caption of referenced model.
     */
    public function testModelCaption(): void
    {
        $user = new Model(null, ['table' => 'user']);
        $user->addField('id');
        $user->addField('name');
        $user = $user->createEntity();
        $user->setId(1)->save();

        $order = new Model();
        $order->addField('id');
        $order->addField('amount', ['default' => 20]);
        $order->addField('user_id');

        $user->withMany('Orders', ['theirModel' => $order, 'caption' => 'My Orders']);

        // test caption of containsOne reference
        $this->assertSame('My Orders', $user->getReference('Orders')->createTheirModel()->getCaption());
        $this->assertSame('My Orders', $user->ref('Orders')->getCaption());
    }

    public function testModelProperty(): void
    {
        $db = new Persistence\Array_();
        $user = new Model($db, ['table' => 'user']);
        $user = $user->createEntity();
        $user->setId(1);
        $user->hasOne('order', ['theirModel' => [Model::class, 'table' => 'order']]);
        $o = $user->ref('order');
        $this->assertSame('order', $o->table);
    }

    public function testRefName1(): void
    {
        $user = new Model(null, ['table' => 'user']);
        $order = new Model();
        $order->addField('user_id');

        $user->withMany('Orders', ['theirModel' => $order]);
        $this->expectException(Exception::class);
        $user->withMany('Orders', ['theirModel' => $order]);
    }

    public function testRefName2(): void
    {
        $user = new Model(null, ['table' => 'user']);

        $user->hasOne('user', ['theirModel' => $user]);
        $this->expectException(Exception::class);
        $user->hasOne('user', ['theirModel' => $user]);
    }

    public function testRefName3(): void
    {
        $db = new Persistence\Array_();
        $order = new Model($db, ['table' => 'order']);
        $order->addReference('archive', ['theirModel' => fn () => new $order(null, ['table' => $order->table . '_archive'])]);
        $this->expectException(Exception::class);
        $order->addReference('archive', ['theirModel' => fn () => new $order(null, ['table' => $order->table . '_archive'])]);
    }

    public function testCustomRef(): void
    {
        $p = new Persistence\Array_();

        $m = new Model($p, ['table' => 'user']);
        $m->addReference('archive', ['theirModel' => fn () => new $m(null, ['table' => $m->table . '_archive'])]);

        $this->assertSame('user_archive', $m->ref('archive')->table);
    }
}
