<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Hintable;

use Phlex\Data\Exception;
use Phlex\Data\Persistence;

class HintableModelArrayTest extends \Phlex\Core\PHPUnit\TestCase
{
    public function testKey(): void
    {
        $model = new Model\Simple();
        $this->assertSame('simple', $model->table);
        $this->assertSame('x', $model->key()->x);
        $this->assertSame('x', Model\Simple::hint()->key()->x);

        $model = new Model\Standard();
        $this->assertSame('prefix_standard', $model->table);
        $this->assertSame('x', $model->key()->x);
        $this->assertSame('yy', $model->key()->y);
        $this->assertSame('id', $model->key()->id);
        $this->assertSame('name', $model->key()->_name);
        $this->assertSame('simpleOne', $model->key()->simpleOne);
        $this->assertSame('simpleMany', $model->key()->simpleMany);
    }

    public function testKeyUndeclaredException(): void
    {
        $model = new Model\Simple();
        $this->expectException(Exception::class);
        $model->key()->undeclared; // @phpstan-ignore-line
    }

    protected function createPersistence(): Persistence
    {
        return new \Phlex\Data\Persistence\Array_();
    }

    protected function createDatabaseForRefTest(): Persistence
    {
        $db = $this->createPersistence();

        $db->atomic(function () use ($db) {
            $simple1 = (new Model\Simple($db))->createEntity()
                ->set(Model\Simple::hint()->key()->x, 'a')
                ->save();
            $simple2 = (new Model\Simple($db))->createEntity()
                ->set(Model\Simple::hint()->key()->x, 'b1')
                ->save();
            $simple3 = (new Model\Simple($db))->createEntity()
                ->set(Model\Simple::hint()->key()->x, 'b2')
                ->save();

            $standardTemplate = (new Model\Standard($db))->createEntity()
                ->set(Model\Standard::hint()->key()->x, 'xx')
                ->set(Model\Standard::hint()->key()->y, 'yy')
                ->set(Model\Standard::hint()->key()->_name, 'zz')
                ->set(Model\Standard::hint()->key()->dtImmutable, new \DateTime('2000-1-1 12:00:00'))
                ->set(Model\Standard::hint()->key()->dtInterface, new \DateTimeImmutable('2000-2-1 12:00:00'))
                ->set(Model\Standard::hint()->key()->dtMulti, new \DateTimeImmutable('2000-3-1 12:00:00'));
            for ($i = 0; $i < 10; ++$i) {
                (clone $standardTemplate)->save()->delete();
            }
            $standard11 = (clone $standardTemplate)
                ->set(Model\Standard::hint()->key()->simpleOneId, $simple1->id)
                ->save();
            $standard12 = (clone $standardTemplate)
                ->set(Model\Standard::hint()->key()->simpleOneId, $simple3->id)
                ->save();

            $simple1
                ->set(Model\Simple::hint()->key()->refId, $standard11->id)
                ->save();
            $simple2
                ->set(Model\Simple::hint()->key()->refId, $standard12->id)
                ->save();
            $simple3
                ->set(Model\Simple::hint()->key()->refId, $standard12->id)
                ->save();
        });

        return $db;
    }

    public function testRefBasic(): void
    {
        $db = $this->createDatabaseForRefTest();

        $model = new Model\Simple($db);
        $this->assertSame(11, (clone $model)->load(1)->ref->id);
        $this->assertSame(12, (clone $model)->load(2)->ref->id);
        $this->assertSame(12, (clone $model)->load(3)->ref->id);
    }

    public function testRefNoData(): void
    {
        $model = new Model\Standard();
        $model->initialize();
        $this->assertInstanceOf(Model\Simple::class, $model->simpleOne);

        // TODO seems like a bug in atk4/data
        $this->markTestSkipped('Phlex Data does not support traversing 1:N reference without persistence'); // @phpstan-ignore-next-line
        $model = new Model\Standard();
        $model->invokeInit();
        $this->assertInstanceOf(Model\Simple::class, $model->simpleMany);
    }

    public function testRefOne(): void
    {
        $db = $this->createDatabaseForRefTest();

        $model = new Model\Standard($db);
        $this->assertInstanceOf(Model\Simple::class, $model->simpleOne);
        $this->assertSame(1, (clone $model)->simpleOne->loadAny()->id);
        $this->assertSame(3, (clone $model)->load(12)->simpleOne->id);
    }

    public function testRefMany(): void
    {
        $db = $this->createDatabaseForRefTest();

        $model = new Model\Standard($db);
        $this->assertInstanceOf(Model\Simple::class, $model->simpleMany);
        $this->assertSame(1, $model->simpleMany->loadAny()->id);
        $this->assertSame(2, $model->load(12)->simpleMany->loadAny()->id);

        $this->assertSame([2 => 2, 3 => 3], array_map(function (Model\Simple $model) {
            return $model->id;
        }, iterator_to_array($model->load(12)->simpleMany)));
    }

    public function testRefManyIsNotEntity(): void
    {
        $db = $this->createDatabaseForRefTest();
        $model = new Model\Standard($db);
        $this->assertFalse($model->load(12)->simpleMany->isEntity());
    }

    public function testPhpstanModelIteratorAggregate(): void
    {
        $db = $this->createDatabaseForRefTest();
        $model = new Model\Simple($db);
        $this->assertIsString((clone $model)->loadAny()->x);
        foreach ($model as $modelItem) {
            $this->assertIsString($modelItem->x);
        }
    }
}
