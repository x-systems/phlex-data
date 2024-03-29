<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Exception;
use Phlex\Data\Model;

/**
 * Tests cases when model have to work with data that does not have ID field.
 */
class ReadOnlyModeTest extends Sql\TestCase
{
    /** @var Model */
    public $m;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $this->m = new Model($this->db, ['table' => 'user', 'readOnly' => true]);

        $this->m->addFields(['name', 'gender']);
    }

    /**
     * Basic operation should work just fine on model without ID.
     */
    public function testBasic(): void
    {
        $m = $this->m->tryLoadAny();
        $this->assertSame('John', $m->get('name'));

        $this->m->setOrder('name', 'desc');
        $m = $this->m->tryLoadAny();
        $this->assertSame('Sue', $m->get('name'));

        $this->assertEquals([1 => 'John', 2 => 'Sue'], $this->m->getTitles());
    }

    /**
     * Read only model can be loaded just fine.
     */
    public function testLoad(): void
    {
        $m = $this->m->load(1);
        $this->assertTrue($m->isLoaded());
    }

    /**
     * Model cannot be saved.
     */
    public function testLoadSave(): void
    {
        $m = $this->m->load(1);
        $m->set('name', 'X');
        $this->expectException(Exception::class);
        $m->save();
    }

    /**
     * Insert should fail too.
     */
    public function testInsert()
    {
        $this->expectException(Exception::class);
        $this->m->insert(['name' => 'Joe']);
    }

    /**
     * Different attempt that should also fail.
     */
    public function testSave1()
    {
        $m = $this->m->tryLoadAny();
        $this->expectException(Exception::class);
        $m->saveWithoutReloading();
    }

    /**
     * Conditions should work fine.
     */
    public function testLoadBy(): void
    {
        $m = $this->m->loadBy('name', 'Sue');
        $this->assertSame('Sue', $m->get('name'));
    }

    public function testLoadCondition(): void
    {
        $this->m->addCondition('name', 'Sue');
        $m = $this->m->loadAny();
        $this->assertSame('Sue', $m->get('name'));
    }

    public function testFailDelete1(): void
    {
        $this->expectException(Exception::class);
        $this->m->delete(1);
    }
}
