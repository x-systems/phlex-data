<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Core\PHPUnit\TestCase;
use Phlex\Data\Model;
use Phlex\Data\Persistence;
use Phlex\Data\Tests\Model\Female;
use Phlex\Data\Tests\Model\Male;

class PersistentSessionTest extends TestCase
{
    private $model;

    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION['session_test'] = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
            ],
        ];

        $persistence = new Persistence\Session('session_test');

        $this->model = new Model($persistence, ['table' => 'user']);
        $this->model->addField('name');
        $this->model->addField('surname');
        $this->model->addField('gender');
    }

    public function testLoadArray(): void
    {
        $model = $this->model;

        $mm = $model->load(1);
        $this->assertSame('John', $mm->get('name'));

        $mm->unload();
        $this->assertFalse($mm->isLoaded());

        $mm = $model->tryLoadAny();
        $this->assertTrue($mm->isLoaded());

        $mm = $model->load(2);
        $this->assertSame('Jones', $mm->get('surname'));
        $mm->set('surname', 'Smith');
        $mm->save();

        $mm = $model->load(1);
        $this->assertSame('John', $mm->get('name'));

        $mm = $model->load(2);
        $this->assertSame('Smith', $mm->get('surname'));
    }

    public function testSaveAndUnload(): void
    {
        $persistence = new Persistence\Session('session_test');

        $model = new Male($persistence, ['table' => 'user']);

        $model = $model->load(1);
        $this->assertTrue($model->isLoaded());
        $model->set('gender', 'F');
        $model->saveWithoutReloading();
        $this->assertTrue($model->isLoaded());

        $model = new Female($persistence, ['table' => 'user']);
        $model = $model->load(1);
        $this->assertTrue($model->isLoaded());

        $this->assertSame([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'F'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
            ],
        ], $_SESSION['session_test']);
    }

    public function testUpdateArray(): void
    {
        $model = $this->model;

        $mm = $model->load(1);
        $mm->set('name', 'Peter');
        $mm->save();

        $mm = $model->load(2);
        $mm->set('surname', 'Smith');
        $mm->save();
        $mm->set('surname', 'QQ');
        $mm->save();

        $this->assertSame([
            'user' => [
                1 => ['name' => 'Peter', 'surname' => 'Smith', 'gender' => 'M'],
                2 => ['name' => 'Sarah', 'surname' => 'QQ', 'gender' => 'F'],
            ],
        ], $_SESSION['session_test']);

        $model = $model->createEntity();
        $model->setMulti(['name' => 'Foo', 'surname' => 'Bar']);
        $model->save();

        $this->assertEquals([
            'user' => [
                1 => ['name' => 'Peter', 'surname' => 'Smith', 'gender' => 'M'],
                2 => ['name' => 'Sarah', 'surname' => 'QQ', 'gender' => 'F'],
                3 => ['name' => 'Foo', 'surname' => 'Bar', 'gender' => null],
            ],
        ], $_SESSION['session_test']);
    }

    public function testInsert(): void
    {
        $model = $this->model;

        $model->insert(['name' => 'Foo', 'surname' => 'Bar']);

        $this->assertEquals([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
                3 => ['name' => 'Foo', 'surname' => 'Bar', 'gender' => null],
            ],
        ], $_SESSION['session_test']);

        $this->assertSame('3', $this->model->persistence->lastInsertId());
    }

    public function testIterator(): void
    {
        $model = $this->model;

        $output = '';

        foreach ($model as $row) {
            $output .= $row->get('name');
        }

        $this->assertSame('JohnSarah', $output);
    }

    /**
     * Test short format.
     */
    public function testShortFormat(): void
    {
        $model = $this->model;

        $mm = $model->load(1);
        $this->assertSame('John', $mm->get('name'));

        $mm = $model->load(2);
        $this->assertSame('Jones', $mm->get('surname'));
        $mm->set('surname', 'Smith');
        $mm->save();

        $mm = $model->load(1);
        $this->assertSame('John', $mm->get('name'));

        $mm = $model->load(2);
        $this->assertSame('Smith', $mm->get('surname'));
    }

    /**
     * Test export.
     */
    public function testExport(): void
    {
        $model = $this->model;

        $this->assertSame([
            1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
            2 => ['id' => 2, 'name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
        ], $model->export());

        $this->assertSame([
            1 => ['surname' => 'Smith'],
            2 => ['surname' => 'Jones'],
        ], $model->export(['surname']));
    }

    /**
     * Test Model->toQuery()->count().
     */
    public function testActionCount(): void
    {
        $model = $this->model;

        $this->assertSame(2, $model->getCount());
    }

    /**
     * Test Model->toQuery()->field().
     */
    public function testQueryField(): void
    {
        $model = $this->model;

        $this->assertSame(2, $model->getCount());

        // use alias as array key if it is set
        $q = $model->toQuery()->field('name', 'first_name');
        $this->assertSame([
            1 => ['first_name' => 'John'],
            2 => ['first_name' => 'Sarah'],
        ], $q->getRows());

        // if alias is not set, then use field name as key
        $q = $model->toQuery()->field('name');
        $this->assertSame([
            1 => ['name' => 'John'],
            2 => ['name' => 'Sarah'],
        ], $q->getRows());
    }
}
