<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

class ReferenceTest extends \Phlex\Core\PHPUnit\TestCase
{
    public function testBasicReferences()
    {
        $user = new Model(null, ['table' => 'user']);
        $user->addField('id');
        $user->addField('name');
        $user->setId(1);

        $order = new Model();
        $order->addField('id');
        $order->addField('amount', ['default' => 20]);
        $order->addField('user_id');

        $user->hasMany('Orders', ['model' => $order, 'caption' => 'My Orders']);
        $o = $user->ref('Orders');

        $this->assertSame(20, $o->get('amount'));
        $this->assertSame(1, $o->get('user_id'));

        $user->hasMany('BigOrders', ['model' => function () {
            $m = new Model();
            $m->addField('amount', ['default' => 100]);
            $m->addField('user_id');

            return $m;
        }]);

        $this->assertSame(100, $user->ref('BigOrders')->get('amount'));
    }

    /**
     * Test caption of referenced model.
     */
    public function testModelCaption()
    {
        $user = new Model(null, ['table' => 'user']);
        $user->addField('id');
        $user->addField('name');
        $user->setId(1);

        $order = new Model();
        $order->addField('id');
        $order->addField('amount', ['default' => 20]);
        $order->addField('user_id');

        $user->hasMany('Orders', ['model' => $order, 'caption' => 'My Orders']);

        // test caption of containsOne reference
        $this->assertSame('My Orders', $user->refModel('Orders')->getCaption());
        $this->assertSame('My Orders', $user->ref('Orders')->getCaption());
    }

    public function testModelProperty()
    {
        $db = new Persistence\Array_();
        $user = new Model($db, ['table' => 'user']);
        $user->setId(1);
        $user->hasOne('order_id', ['model' => [Model::class, 'table' => 'order']]);
        $o = $user->ref('order_id');
        $this->assertSame('order', $o->table);
    }

    public function testRefName1()
    {
        $user = new Model(null, ['table' => 'user']);
        $order = new Model();
        $order->addField('user_id');

        $user->hasMany('Orders', ['model' => $order]);
        $this->expectException(Exception::class);
        $user->hasMany('Orders', ['model' => $order]);
    }

    public function testRefName2()
    {
        $order = new Model(null, ['table' => 'order']);
        $user = new Model(null, ['table' => 'user']);

        $user->hasOne('user_id', ['model' => $user]);
        $this->expectException(Exception::class);
        $user->hasOne('user_id', ['model' => $user]);
    }

    public function testRefName3()
    {
        $db = new Persistence\Array_();
        $order = new Model($db, ['table' => 'order']);
        $order->addRef('archive', ['model' => function ($m) {
            return $m->newInstance(null, ['table' => $m->table . '_archive']);
        }]);
        $this->expectException(Exception::class);
        $order->addRef('archive', ['model' => function ($m) {
            return $m->newInstance(null, ['table' => $m->table . '_archive']);
        }]);
    }

    public function testCustomRef()
    {
        $p = new Persistence\Array_();

        $m = new Model($p, ['table' => 'user']);
        $m->addRef('archive', ['model' => function ($m) {
            return $m->newInstance(null, ['table' => $m->table . '_archive']);
        }]);

        $this->assertSame('user_archive', $m->ref('archive')->table);
    }
}
