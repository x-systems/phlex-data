<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

class Model_Rate extends Model
{
    public $table = 'rate';

    protected function doInitialize(): void
    {
        parent::doInitialize();
        $this->addField('dat');
        $this->addField('bid', ['type' => 'float']);
        $this->addField('ask', ['type' => 'float']);
    }
}
class Model_Item extends Model
{
    public $table = 'item';

    protected function doInitialize(): void
    {
        parent::doInitialize();
        $this->addField('name');
        $this->hasOne('parent_item', ['theirModel' => [self::class]])
            ->addTitle();
    }
}
class Model_Item2 extends Model
{
    public $table = 'item';

    protected function doInitialize(): void
    {
        parent::doInitialize();
        $this->addField('name');
        $i2 = $this->join('item2.item_id');
        $i2->hasOne('parent_item', ['theirModel' => [self::class]])
            ->addTitle();
    }
}
class Model_Item3 extends Model
{
    public $table = 'item';

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $m = new self();

        $this->addField('name');
        $this->addField('age');
        $i2 = $this->join('item2.item_id');
        $i2->hasOne('parent_item', ['theirModel' => $m, 'table_alias' => 'parent'])
            ->withTitle();

        $this->withMany('Child', ['theirModel' => $m, 'theirKey' => 'parent_item_id', 'table_alias' => 'child'])
            ->addField('child_age', ['aggregate' => 'sum', 'field' => 'age']);
    }
}

class RandomTest extends Sql\TestCase
{
    public function testRate()
    {
        $this->setDb([
            'rate' => [
                ['dat' => '18/12/12', 'bid' => 3.4, 'ask' => 9.4],
                ['dat' => '12/12/12', 'bid' => 8.3, 'ask' => 9.2],
            ],
        ]);

        $m = new Model_Rate($this->db);

        $this->assertEquals(2, $m->getCount());
    }

    public function testTitleImport(): void
    {
        $this->setDb([
            'user' => [
                '_' => ['name' => 'John', 'salary' => 29],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addFields(['name', ['salary', 'default' => 10]]);

        $m->import([['name' => 'Peter'], ['name' => 'Steve', 'salary' => 30]]);
        $m->insert(['name' => 'Sue']);
        $m->insert(['name' => 'John', 'salary' => 40]);

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'Peter', 'salary' => 10],
                2 => ['id' => 2, 'name' => 'Steve', 'salary' => 30],
                3 => ['id' => 3, 'name' => 'Sue', 'salary' => 10],
                4 => ['id' => 4, 'name' => 'John', 'salary' => 40],
            ],
        ], $this->getDb());
    }

    public function testAddFields(): void
    {
        $this->setDb([
            'user' => [
                1 => ['name' => 'John', 'login' => 'john@example.com'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addFields(['name', 'login'], ['default' => 'unknown']);

        $m->insert(['name' => 'Peter']);
        $m->insert([]);

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'login' => 'john@example.com'],
                2 => ['id' => 2, 'name' => 'Peter', 'login' => 'unknown'],
                3 => ['id' => 3, 'name' => 'unknown', 'login' => 'unknown'],
            ],
        ], $this->getDb());
    }

    public function testAddFields2(): void
    {
        $this->setDb([
            'user' => [
                1 => ['name' => 'John', 'last_name' => null, 'login' => null, 'salary' => null, 'tax' => null, 'vat' => null],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addFields(['name'], ['default' => 'anonymous']);
        $m->addFields([
            'last_name',
            'login' => ['default' => 'unknown'],
            'salary' => ['type' => 'money', CustomField::class, 'default' => 100],
            ['tax', CustomField::class, 'type' => 'money', 'default' => 20],
            'vat' => new CustomField(['type' => 'money', 'default' => 15]),
        ]);

        $m->insert([]);

        $this->assertEquals([
            ['id' => 1, 'name' => 'John', 'last_name' => null, 'login' => null, 'salary' => null, 'tax' => null, 'vat' => null],
            ['id' => 2, 'name' => 'anonymous', 'last_name' => null, 'login' => 'unknown', 'salary' => 100, 'tax' => 20, 'vat' => 15],
        ], $m->export());

        $m = $m->load(2);
        $this->assertTrue(is_float($m->get('salary')));
        $this->assertTrue(is_float($m->get('tax')));
        $this->assertTrue(is_float($m->get('vat')));
    }

    public function testSameTable()
    {
        $this->setDb([
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'parent_item_id' => 1],
                2 => ['id' => 2, 'name' => 'Sue', 'parent_item_id' => 1],
                3 => ['id' => 3, 'name' => 'Smith', 'parent_item_id' => 2],
            ],
        ]);

        $m = new Model_Item($this->db, ['table' => 'item']);

        $this->assertSame(
            ['id' => 3, 'name' => 'Smith', 'parent_item_id' => 2, 'parent_item_name' => 'Sue'],
            $m->load(3)->get()
        );
    }

    public function testSameTable2(): void
    {
        $this->setDb([
            'item' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Sue'],
                3 => ['id' => 3, 'name' => 'Smith'],
            ],
            'item2' => [
                1 => ['id' => 1, 'item_id' => 1, 'parent_item_id' => 1],
                2 => ['id' => 2, 'item_id' => 2, 'parent_item_id' => 1],
                3 => ['id' => 3, 'item_id' => 3, 'parent_item_id' => 2],
            ],
        ]);

        $m = new Model_Item2($this->db, ['table' => 'item']);

        $this->assertSame(
            ['id' => 3, 'name' => 'Smith', 'parent_item_id' => 2, 'parent_item_name' => 'Sue'],
            $m->load(3)->get()
        );
    }

    public function testSameTable3(): void
    {
        $this->setDb([
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'age' => 18],
                2 => ['id' => 2, 'name' => 'Sue', 'age' => 20],
                3 => ['id' => 3, 'name' => 'Smith', 'age' => 24],
            ],
            'item2' => [
                1 => ['id' => 1, 'item_id' => 1, 'parent_item_id' => 1],
                2 => ['id' => 2, 'item_id' => 2, 'parent_item_id' => 1],
                3 => ['id' => 3, 'item_id' => 3, 'parent_item_id' => 2],
            ],
        ]);

        $m = new Model_Item3($this->db, ['table' => 'item']);

        $this->assertEquals(
            ['id' => '2', 'name' => 'Sue', 'parent_item_id' => 1, 'parent_item_name' => 'John', 'age' => '20', 'child_age' => 24],
            $m->load(2)->get()
        );

        $this->assertEquals(1, $m->load(2)->ref('Child', ['table_alias' => 'pp'])->getCount());
        $this->assertSame('John', $m->load(2)->ref('parent_item', ['table_alias' => 'pp'])->get('name'));
    }

    public function testUpdateCondition(): void
    {
        $this->setDb([
            'item' => [
                ['name' => 'John'],
                ['name' => 'Sue'],
                ['name' => 'Smith'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'item']);
        $m->addField('name');
        $m = $m->load(2);

        $m->onHook(Persistence\Query::HOOK_AFTER_UPDATE, static function ($m, $update, $st) {
            // we can use afterUpdate to make sure that record was updated

            if (!$st->rowCount()) {
                throw (new \Phlex\Core\Exception('Update didn\'t affect any records'))
                    ->addMoreInfo('query', $update->getDebugQuery())
                    ->addMoreInfo('statement', $st)
                    ->addMoreInfo('model', $m)
                    ->addMoreInfo('conditions', $m->conditions);
            }
        });

        $this->assertSame('Sue', $m->get('name'));

        $dbData = [
            'item' => [
                1 => ['id' => 1, 'name' => 'John'],
            ],
        ];
        $this->setDb($dbData);

        $m->set('name', 'Peter');

        try {
            $m->save();
            $e = null;
        } catch (\Exception $e) {
        }

        $this->assertNotNull($e);
        $this->assertEquals($dbData, $this->getDb());
    }

    public function testHookBreakers(): void
    {
        $this->setDb([
            'item' => [
                ['name' => 'John'],
                ['name' => 'Sue'],
                ['name' => 'Smith'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');

        $m->onHook(Model::HOOK_BEFORE_SAVE, static function (Model $m) {
            $m->breakHook(false);
        });

        $m->onHook(Model::HOOK_BEFORE_LOAD, static function (Model $m, int $id) {
            $m->setId($id);
            $m->set('name', 'rec #' . $id);
            $m->breakHook(false);
        });

        $m->onHook(Model::HOOK_BEFORE_DELETE, static function (Model $m, int $id) {
            $m->unload();
            $m->breakHook(false);
        });

        $m = $m->createEntity();
        $m->set('name', 'john');
        $m->save();

        $m = $m->load(3);
        $this->assertSame('rec #3', $m->get('name'));

        $m->delete();
    }

    public function testIssue220()
    {
        $m = new Model_Item($this->db);

        $this->expectException(Exception::class);
        $m->hasOne('foo', ['theirModel' => [Model_Item::class], 'ourKey' => 'foo'])
            ->addTitle(['key' => 'foo']); // field foo already exists, so we can't add title with same name
    }

    // @todo: activate when morphing of seeds to more specific class tested
    // e.g. \Phlex\Data\Model\Field should be transformed to Phlex\Data\Persistence\Sql\Field for the persistence in Model::addField
//     public function testNonSqlFieldClass()
//     {
//         $db = new Persistence\Sql($this->db->connection);
//         $this->setDb([
//             'rate' => [
//                 ['dat' => '18/12/12', 'bid' => 3.4, 'ask' => 9.4, 'x1' => 'y1', 'x2' => 'y2'],
//             ],
//         ]);

//         $m = new Model_Rate($db);
//         $m->addField('x1', new \Phlex\Data\Persistence\Sql\Field());
//         $m->addField('x2', new \Phlex\Data\Model\Field());
//         $m->load(1);

//         $this->assertEquals(3.4, $m->get('bid'));
//         $this->assertSame('y1', $m->get('x1'));
//         $this->assertSame('y2', $m->get('x2'));
//     }

    public function testModelCaption(): void
    {
        $m = new Model($this->db, ['table' => 'user']);

        // caption is not set, so generate it from class name Model
        $this->assertSame('Phlex Data Model', $m->getCaption());

        // caption is set
        $m->caption = 'test';
        $this->assertSame('test', $m->getCaption());
    }

    public function testGetTitle(): void
    {
        $this->setDb([
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'parent_item_id' => 1],
                2 => ['id' => 2, 'name' => 'Sue', 'parent_item_id' => 1],
            ],
        ]);

        $m = new Model_Item($this->db, ['table' => 'item']);

        $this->assertSame([1 => 'John', 2 => 'Sue'], $m->getTitles()); // all titles

        $mm = $m->createEntity();

        // default titleKey = name
        $this->assertNull($mm->getTitle()); // not loaded model returns null

        $mm = $m->load(2);
        $this->assertSame('Sue', $mm->getTitle()); // loaded returns titleKey value

        // set custom titleKey
        $mm->titleKey = 'parent_item_id';
        $this->assertEquals(1, $mm->getTitle()); // returns parent_item_id value

        // set custom titleKey as titleKey from linked model
        $mm->titleKey = 'parent_item_name';
        $this->assertSame('John', $mm->getTitle()); // returns parent record titleKey

        // no titleKey set - return id value
        $mm->titleKey = null; // @phpstan-ignore-line
        $this->assertEquals(2, $mm->getTitle()); // loaded returns id value

        // expression as title field
        $m->addExpression('my_name', '[id]');
        $m->titleKey = 'my_name';
        $mm = $m->load(2);
        $this->assertEquals(2, $mm->getTitle()); // loaded returns id value
    }

    /**
     * Test export.
     */
    public function testExport(): void
    {
        $this->setDb([
            'user' => [
                2 => ['code' => 10, 'name' => 'John'],
                5 => ['code' => 20, 'name' => 'Sarah'],
            ],
        ]);

        // model without id field
        $m1 = new Model($this->db, ['table' => 'user', 'primaryKey' => false]);
        $m1->addField('code');
        $m1->addField('name');

        // model with id field
        $m2 = new Model($this->db, ['table' => 'user']);
        $m2->addField('code');
        $m2->addField('name');

        // normal export
        $this->assertEquals([
            0 => ['code' => 10, 'name' => 'John'],
            1 => ['code' => 20, 'name' => 'Sarah'],
        ], $m1->export());

        $this->assertEquals([
            0 => ['id' => 2, 'code' => 10, 'name' => 'John'],
            1 => ['id' => 5, 'code' => 20, 'name' => 'Sarah'],
        ], $m2->export());

        // export fields explicitly set
        $this->assertSame([
            0 => ['name' => 'John'],
            1 => ['name' => 'Sarah'],
        ], $m1->export(['name']));

        $this->assertSame([
            0 => ['name' => 'John'],
            1 => ['name' => 'Sarah'],
        ], $m2->export(['name']));

        // key field explicitly set
        $this->assertEquals([
            10 => ['code' => 10, 'name' => 'John'],
            20 => ['code' => 20, 'name' => 'Sarah'],
        ], $m1->export(null, 'code'));

        $this->assertEquals([
            10 => ['id' => 2, 'code' => 10, 'name' => 'John'],
            20 => ['id' => 5, 'code' => 20, 'name' => 'Sarah'],
        ], $m2->export(null, 'code'));

        // field names and key field explicitly set
        $this->assertSame([
            10 => ['name' => 'John'],
            20 => ['name' => 'Sarah'],
        ], $m1->export(['name'], 'code'));

        $this->assertSame([
            10 => ['name' => 'John'],
            20 => ['name' => 'Sarah'],
        ], $m2->export(['name'], 'code'));

        // field names include key field
        $this->assertEquals([
            10 => ['code' => 10, 'name' => 'John'],
            20 => ['code' => 20, 'name' => 'Sarah'],
        ], $m1->export(['code', 'name'], 'code'));

        $this->assertEquals([
            10 => ['code' => 10, 'name' => 'John'],
            20 => ['code' => 20, 'name' => 'Sarah'],
        ], $m2->export(['code', 'name'], 'code'));
    }

    public function testDuplicateSaveNew(): void
    {
        $this->setDb([
            'rate' => [
                ['dat' => '18/12/12', 'bid' => 3.4, 'ask' => 9.4],
                ['dat' => '12/12/12', 'bid' => 8.3, 'ask' => 9.2],
            ],
        ]);

        $m = new Model_Rate($this->db);

        $m->load(1)->duplicate()->save();

        $this->assertSame([
            ['id' => 1, 'dat' => '18/12/12', 'bid' => 3.4, 'ask' => 9.4],
            ['id' => 2, 'dat' => '12/12/12', 'bid' => 8.3, 'ask' => 9.2],
            ['id' => 3, 'dat' => '18/12/12', 'bid' => 3.4, 'ask' => 9.4],
        ], $m->export());
    }

    public function testDuplicateWithIdArgumentException(): void
    {
        $m = new Model_Rate();
        $this->expectException(Exception::class);
        $m->duplicate(2)->save();
    }

    public function testTableNameDots(): void
    {
        $d = new Model($this->db, ['table' => 'db2.doc']);
        $d->addField('name');

        $m = new Model($this->db, ['table' => 'db1.user']);
        $m->addField('name');

        $d->hasOne('user', ['theirModel' => $m])->addTitle();
        $m->withMany('Documents', ['theirModel' => $d]);

        $d->addCondition('user_name', 'Sarah');

        $this->assertSameSql(
            'select "id","name","user_id",(select "name" from "db1"."user" where "id" = "db2"."doc"."user_id") "user_name" from "db2"."doc" where (select "name" from "db1"."user" where "id" = "db2"."doc"."user_id") = :a',
            $d->toQuery()->select()->render()
        );
    }
}

class CustomField extends \Phlex\Data\Persistence\Sql\Field
{
}
