<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Model;
use Phlex\Data\Persistence;

class StaticPersistenceTest extends \Phlex\Core\PHPUnit\TestCase
{
    public function testBasicStatic(): void
    {
        $p = new Persistence\Static_(['hello', 'world']);

        // default title field
        $m = new Model($p);
        $m = $m->load(1);
        $this->assertSame('world', $m->get('name'));

        // custom title field and try loading from same static twice
        $m = new Model($p); // , ['titleKey' => 'foo']);
        $m = $m->load(1);
        $this->assertSame('world', $m->get('name')); // still 'name' here not 'foo'
    }

    public function testArrayOfArrays(): void
    {
        $p = new Persistence\Static_([['hello', 'xx', true], ['world', 'xy', false]]);
        $m = new Model($p);

        $m = $m->load(1);

        $this->assertSame('world', $m->get('name'));
        $this->assertSame('xy', $m->get('field1'));
        $this->assertFalse($m->get('field2'));
    }

    public function testArrayOfHashes(): void
    {
        $p = new Persistence\Static_([['foo' => 'hello'], ['foo' => 'world']]);
        $m = new Model($p);

        $m = $m->load(1);

        $this->assertSame('world', $m->get('foo'));
    }

    public function testIdArg(): void
    {
        $p = new Persistence\Static_([['id' => 20, 'foo' => 'hello'], ['id' => 21, 'foo' => 'world']]);
        $m = new Model($p);

        $m = $m->load(21);

        $this->assertSame('world', $m->get('foo'));
    }

    public function testIdKey(): void
    {
        $p = new Persistence\Static_([20 => ['foo' => 'hello'], 21 => ['foo' => 'world']]);
        $m = new Model($p);

        $m = $m->load(21);

        $this->assertSame('world', $m->get('foo'));
    }

    public function testEmpty(): void
    {
        $p = new Persistence\Static_([]);
        $m = new Model($p);

        $m = $m->tryLoadAny();

        $this->assertFalse($m->isLoaded());
    }

    public function testCustomField(): void
    {
        $p = new Persistence\Static_([['foo' => 'hello'], ['foo' => 'world']]);
        $m = new StaticPersistenceModel($p);

        $this->assertSame('custom field', $m->getField('foo')->caption);

        $p = new Persistence\Static_([['foo' => 'hello', 'bar' => 'world']]);
        $m = new StaticPersistenceModel($p);
        $this->assertSame('foo', $m->titleKey);
    }

    public function testTitleOrName(): void
    {
        $p = new Persistence\Static_([['foo' => 'hello', 'bar' => 'world']]);
        $m = new Model($p);
        $this->assertSame('foo', $m->titleKey);

        $p = new Persistence\Static_([['foo' => 'hello', 'name' => 'x']]);
        $m = new Model($p);
        $this->assertSame('name', $m->titleKey);

        $p = new Persistence\Static_([['foo' => 'hello', 'title' => 'x']]);
        $m = new Model($p);
        $this->assertSame('title', $m->titleKey);
    }

    public function testFieldTypes(): void
    {
        $p = new Persistence\Static_([[
            'name' => 'hello',
            'test_int' => 123,
            'test_float' => 123.45,
            'test_date' => new \DateTime(),
            'test_array' => ['a', 'b', 'c'],
            'test_object' => new \DateInterval('P1Y'),
            'test_str_1' => 'abc',
            'test_str_2' => '123',
            'test_str_3' => '123.45',
        ]]);
        $m = new Model($p);

        $this->assertSame('integer', $m->getField('test_int')->type);
        $this->assertSame('float', $m->getField('test_float')->type);
        $this->assertSame('datetime', $m->getField('test_date')->type);
        $this->assertSame('array', $m->getField('test_array')->type);
        $this->assertSame('object', $m->getField('test_object')->type);

        // string is default type, so it is null
        $this->assertNull($m->getField('name')->type);
        $this->assertNull($m->getField('test_str_1')->type);
        $this->assertNull($m->getField('test_str_2')->type);
        $this->assertNull($m->getField('test_str_3')->type);
    }
}

class StaticPersistenceModel extends Model
{
    public $titleKey = 'foo';

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->addField('foo', ['caption' => 'custom field']);
    }
}
