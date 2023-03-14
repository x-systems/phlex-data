<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;
use Phlex\Data\Tests\Model\Client;
use Phlex\Data\Tests\Model\User;

class BusinessModelTest extends \Phlex\Core\PHPUnit\TestCase
{
    public function testConstructFields(): void
    {
        $m = new Model();
        $m->addField('name');

        $f = $m->getField('name');
        $this->assertSame('name', $f->elementId);

        $m->addField('surname', new Model\Field());
        $f = $m->getField('surname');
        $this->assertSame('surname', $f->elementId);
    }

    public function testFieldAccess(): void
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('surname');
        $m = $m->createEntity();

        $m->set('name', 5);
        $this->assertSame(5, $m->get('name'));

        $m->set('surname', 'Bilbo');
        $this->assertSame(5, $m->get('name'));
        $this->assertSame('Bilbo', $m->get('surname'));

        $this->assertSame(['name' => 5, 'surname' => 'Bilbo'], $m->get());
    }

    public function testNoFieldException(): void
    {
        $m = new Model();
        $m = $m->createEntity();
        $this->expectException(Exception::class);
        $m->set('name', 5);
    }

    public function testDirty(): void
    {
        $m = new Model();
        $m->addField('name');
        $m = $m->createEntity(['name' => 5]);

        $m->set('name', 5);
        $this->assertSame([], $m->getEntry()->getDirty());

        $m->set('name', 10);
        $this->assertSame(['name' => 5], $m->getEntry()->getLoaded());

        $m->set('name', 15);
        $this->assertSame(['name' => 5], $m->getEntry()->getLoaded());

        $m->set('name', 5);
        $this->assertSame([], $m->getEntry()->getDirty());

        $m->set('name', '5');
        $this->assertSame([], $m->getEntry()->getDirty());

        $m->set('name', '6');
        $this->assertSame(['name' => 5], $m->getEntry()->getLoaded());
        $m->set('name', '5');
        $this->assertSame([], $m->getEntry()->getDirty());

        $m->set('name', '5.0');
        $this->assertSame(['name' => 5], $m->getEntry()->getLoaded());

        $m = $m->createEntity(['name' => '']);
        $m->set('name', '');
        $this->assertSame([], $m->getEntry()->getDirty());

        $m = $m->createEntity(['name' => '5']);
        $m->set('name', 5);
        $this->assertSame([], $m->getEntry()->getDirty());
        $m->set('name', 6);
        $this->assertSame(['name' => '5'], $m->getEntry()->getLoaded());
        $m->set('name', 5);
        $this->assertSame([], $m->getEntry()->getDirty());
        $m->set('name', '5');
        $this->assertSame([], $m->getEntry()->getDirty());

        $m = $m->createEntity(['name' => 4.28]);
        $m->set('name', '4.28');
        $this->assertSame([], $m->getEntry()->getDirty());
        $m->set('name', '5.28');
        $this->assertSame(['name' => 4.28], $m->getEntry()->getLoaded());
        $m->set('name', 4.28);
        $this->assertSame([], $m->getEntry()->getDirty());

        // now with defaults
        $m = new Model();
        $f = $m->addField('name', ['default' => 'John']);
        $m = $m->createEntity();
        $this->assertSame('John', $f->default);

        $this->assertSame('John', $m->get('name'));

        $m->set('name', null);
        $this->assertSame([], $m->getEntry()->getLoaded());
        $this->assertSame(['name' => null], $m->getEntry()->getDirty());
        $this->assertNull($m->get('name'));

        $m->reset('name');
        $this->assertSame('John', $m->get('name'));
    }

    public function testDefaultInit(): void
    {
        $p = new Persistence\Array_();
        $m = new Model($p);
        $m = $m->createEntity(['id' => 20]);

        $this->assertNotNull($m->getField('id'));

        $this->assertEquals(20, $m->getId());
    }

    public function testException1(): void
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('surname');
        $m->setActiveFields(['surname']);
        $m = $m->createEntity();

        $this->expectException(Exception::class);
        $m->set('name', 5);
    }

    public function testException1Fixed(): void
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('surname');
        $m->setActiveFields(['surname']);
        $e = $m->createEntity();

        $e->allFields();

        $e->set('name', 5);
        $this->assertSame(5, $e->get('name'));
    }

    /**
     * Sets title field.
     */
    public function testSetTitle(): void
    {
        $m = new Model();
        $m->addField('name');
        $m = $m->createEntity();
        $m->set('name', 'foo');
        $this->assertSame('foo', $m->get('name'));

        $m->set('name', 'baz');
        $this->assertSame('baz', $m->get('name'));
    }

    /**
     * Fields can't be numeric.
     */
    public function testException2(): void
    {
        $m = new Model();
        $m = $m->createEntity();
        $this->expectException(\Error::class);
        $m->set(0, 'foo');
    }

    public function testException2a(): void
    {
        $m = new Model();
        $m = $m->createEntity();
        $this->expectException(Exception::class);
        $m->set('3', 'foo');
    }

    public function testException2b(): void
    {
        $m = new Model();
        $m = $m->createEntity();
        $this->expectException(Exception::class);
        $m->set('3b', 'foo');
    }

    public function testException2c(): void
    {
        $m = new Model();
        $m = $m->createEntity();
        $this->expectException(Exception::class);
        $m->set('', 'foo');
    }

    public function testClass1(): void
    {
        $p = new Persistence\Array_();
        $c = new Client($p);
        $c = $c->createEntity();
        $this->assertEquals(10, $c->get('order'));
    }

    public function testNormalize(): void
    {
        $m = new Model();
        $m->addField('name', ['type' => 'string']);
        $m->addField('age', ['type' => 'int']);
        $m->addField('data');
        $m = $m->createEntity();

        $m->set('name', '');
        $this->assertSame('', $m->get('name'));

        $m->set('age', '');
        $this->assertNull($m->get('age'));

        $m->set('data', '');
        $this->assertSame('', $m->get('data'));
    }

    public function testExampleFromDoc(): void
    {
        $m = new User();

        $m->addField('salary', ['default' => 1000]);
        $m = $m->createEntity();

        $this->assertSame(1000, $m->get('salary'));
        $this->assertFalse($m->isDirty('salary'));

        // Next we load record from $db
        $m = $m->createEntity(['salary' => 2000]);
        $this->assertSame(2000, $m->get('salary'));
        $this->assertFalse($m->isDirty('salary'));

        $m->set('salary', 3000);
        $this->assertSame(3000, $m->get('salary'));
        $this->assertTrue($m->isDirty('salary'));

        $m->reset('salary');
        $this->assertSame(2000, $m->get('salary'));
        $this->assertFalse($m->isDirty('salary'));
    }
}
