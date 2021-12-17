<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;
use Phlex\Data\Tests\Model\Female as Female;
use Phlex\Data\Tests\Model\Male as Male;

class PersistentArrayTest extends \Phlex\Core\PHPUnit\TestCase
{
    private function getInternalPersistenceData(Persistence\Array_ $db): array
    {
        return $this->getProtected($db, 'data');
    }

    public function testLoadArray(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);
        $m = new Model($p, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $mm = $m->load(1);
        $this->assertSame('John', $mm->get('name'));

        $mm->unload();
        $this->assertFalse($mm->isLoaded());

        $mm = $m->tryLoadAny();
        $this->assertTrue($mm->isLoaded());

        $mm = $m->load(2);
        $this->assertSame('Jones', $mm->get('surname'));
        $mm->set('surname', 'Smith');
        $mm->save();

        $mm = $m->load(1);
        $this->assertSame('John', $mm->get('name'));

        $mm = $m->load(2);
        $this->assertSame('Smith', $mm->get('surname'));
    }

    public function testSaveAndUnload(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
            ],
        ]);
        $m = new Male($p, ['table' => 'user']);

        $m = $m->load(1);
        $this->assertTrue($m->isLoaded());
        $m->set('gender', 'F');
        $m->saveAndUnload();
        $this->assertFalse($m->isLoaded());

        $m = new Female($p, ['table' => 'user']);
        $m = $m->load(1);
        $this->assertTrue($m->isLoaded());

        $this->assertSame([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'F'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
            ],
        ], $this->getInternalPersistenceData($p));
    }

    public function testUpdateArray(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);
        $m = new Model($p, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $mm = $m->load(1);
        $mm->set('name', 'Peter');
        $mm->save();

        $mm = $m->load(2);
        $mm->set('surname', 'Smith');
        $mm->save();
        $mm->set('surname', 'QQ');
        $mm->save();

        $this->assertSame([
            'user' => [
                1 => ['name' => 'Peter', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'QQ'],
            ],
        ], $this->getInternalPersistenceData($p));

        $m = $m->createEntity();
        $m->setMulti(['name' => 'Foo', 'surname' => 'Bar']);
        $m->save();

        $this->assertEquals([
            'user' => [
                1 => ['name' => 'Peter', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'QQ'],
                3 => ['name' => 'Foo', 'surname' => 'Bar'],
            ],
        ], $this->getInternalPersistenceData($p));
    }

    public function testInsert(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);
        $m = new Model($p, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $m->insert(['name' => 'Foo', 'surname' => 'Bar']);

        $this->assertEquals([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
                3 => ['name' => 'Foo', 'surname' => 'Bar'],
            ],
        ], $this->getInternalPersistenceData($p));

        $this->assertSame('3', $p->lastInsertId());
    }

    public function testIterator(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);
        $m = new Model($p, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $output = '';

        foreach ($m as $row) {
            $output .= $row->get('name');
        }

        $this->assertSame('JohnSarah', $output);
    }

    /**
     * Test short format.
     */
    public function testShortFormat(): void
    {
        $p = new Persistence\Array_([
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ]);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $mm = $m->load(1);
        $this->assertSame('John', $mm->get('name'));

        $mm = $m->load(2);
        $this->assertSame('Jones', $mm->get('surname'));
        $mm->set('surname', 'Smith');
        $mm->save();

        $mm = $m->load(1);
        $this->assertSame('John', $mm->get('name'));

        $mm = $m->load(2);
        $this->assertSame('Smith', $mm->get('surname'));
    }

    /**
     * Test export.
     */
    public function testExport(): void
    {
        $p = new Persistence\Array_([
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ]);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $this->assertSame([
            1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            2 => ['id' => 2, 'name' => 'Sarah', 'surname' => 'Jones'],
        ], $m->export());

        $this->assertSame([
            1 => ['surname' => 'Smith'],
            2 => ['surname' => 'Jones'],
        ], $m->export(['surname']));
    }

    /**
     * Test Model->toQuery()->count().
     */
    public function testActionCount(): void
    {
        $p = new Persistence\Array_([
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ]);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $this->assertSame(2, $m->getCount());
    }

    /**
     * Test Model->toQuery()->field().
     */
    public function testQueryField(): void
    {
        $p = new Persistence\Array_([
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ]);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $this->assertSame(2, $m->getCount());

        // use alias as array key if it is set
        $q = $m->toQuery()->field('name', 'first_name');
        $this->assertSame([
            1 => ['first_name' => 'John'],
            2 => ['first_name' => 'Sarah'],
        ], $q->getRows());

        // if alias is not set, then use field name as key
        $q = $m->toQuery()->field('name');
        $this->assertSame([
            1 => ['name' => 'John'],
            2 => ['name' => 'Sarah'],
        ], $q->getRows());
    }

    /**
     * Test Model->addCondition operator LIKE.
     */
    public function testLike(): void
    {
        $dbData = ['countries' => [
            1 => ['name' => 'ABC9', 'code' => 11, 'country' => 'Ireland', 'active' => 1],
            2 => ['name' => 'ABC8', 'code' => 12, 'country' => 'Ireland', 'active' => 0],
            3 => ['name' => null, 'code' => 13, 'country' => 'Latvia', 'active' => 1],
            4 => ['name' => 'ABC6', 'code' => 14, 'country' => 'UK', 'active' => 0],
            5 => ['name' => 'ABC5', 'code' => 15, 'country' => 'UK', 'active' => 0],
            6 => ['name' => 'ABC4', 'code' => 16, 'country' => 'Ireland', 'active' => 1],
            7 => ['name' => 'ABC3', 'code' => 17, 'country' => 'Latvia', 'active' => 0],
            8 => ['name' => 'ABC2', 'code' => 18, 'country' => 'Russia', 'active' => 1],
            9 => ['name' => null, 'code' => 19, 'country' => 'Latvia', 'active' => 1],
            10 => ['code' => null, 'country' => 'Germany', 'active' => 1],
        ]];

        $dbDataCountries = $dbData['countries'];
        foreach ($dbDataCountries as $k => $v) {
            $dbDataCountries[$k] = array_merge(['id' => $k], $v);
        }

        $p = new Persistence\Array_($dbData);
        $m = new Model($p, ['table' => 'countries']);
        $m->addField('code', ['type' => 'integer']);
        $m->addField('country');
        $m->addField('active', ['type' => 'boolean']);

        // case : str%
        $m->addCondition('country', 'LIKE', 'La%');
        $result = $m->toQuery()->select()->getRows();
        $this->assertCount(3, $result);
        $this->assertSame($dbDataCountries[3], $result[3]);
        $this->assertSame($dbDataCountries[7], $result[7]);
        $this->assertSame($dbDataCountries[9], $result[9]);
        unset($result);

        // case : str% NOT LIKE
        $m->scope()->clear();
        $m->addCondition('country', 'NOT LIKE', 'La%');
        $result = $m->toQuery()->select()->getRows();
        $this->assertCount(7, $m->export());
        $this->assertSame($dbDataCountries[1], $result[1]);
        $this->assertSame($dbDataCountries[2], $result[2]);
        $this->assertSame($dbDataCountries[4], $result[4]);
        $this->assertSame($dbDataCountries[5], $result[5]);
        $this->assertSame($dbDataCountries[6], $result[6]);
        $this->assertSame($dbDataCountries[8], $result[8]);
        unset($result);

        // case : %str
        $m->scope()->clear();
        $m->addCondition('country', 'LIKE', '%ia');
        $result = $m->toQuery()->select()->getRows();
        $this->assertCount(4, $result);
        $this->assertSame($dbDataCountries[3], $result[3]);
        $this->assertSame($dbDataCountries[7], $result[7]);
        $this->assertSame($dbDataCountries[8], $result[8]);
        $this->assertSame($dbDataCountries[9], $result[9]);
        unset($result);

        // case : %str%
        $m->scope()->clear();
        $m->addCondition('country', 'LIKE', '%a%');
        $result = $m->toQuery()->select()->getRows();
        $this->assertCount(8, $result);
        $this->assertSame($dbDataCountries[1], $result[1]);
        $this->assertSame($dbDataCountries[2], $result[2]);
        $this->assertSame($dbDataCountries[3], $result[3]);
        $this->assertSame($dbDataCountries[6], $result[6]);
        $this->assertSame($dbDataCountries[7], $result[7]);
        $this->assertSame($dbDataCountries[8], $result[8]);
        $this->assertSame($dbDataCountries[9], $result[9]);
        unset($result);

        // case : boolean field
        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '0');
        $this->assertCount(4, $m->export());

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '1');
        $this->assertCount(6, $m->export());

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '%0%');
        $this->assertCount(4, $m->export());

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '%1%');
        $this->assertCount(6, $m->export());

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '%999%');
        $this->assertCount(0, $m->export());

        $m->scope()->clear();
        $m->addCondition('active', 'LIKE', '%ABC%');
        $this->assertCount(0, $m->export());

        // null value
        $m->scope()->clear();
        $m->addCondition('code', '=', null);
        $this->assertCount(1, $m->export());

        $m->scope()->clear();
        $m->addCondition('code', '!=', null);
        $this->assertCount(9, $m->export());
    }

    /**
     * Test Model->addCondition operator REGEXP.
     */
    public function testConditions(): void
    {
        $dbData = ['countries' => [
            1 => ['id' => 1, 'name' => 'ABC9', 'code' => 11, 'country' => 'Ireland', 'active' => 1],
            2 => ['id' => 2, 'name' => 'ABC8', 'code' => 12, 'country' => 'Ireland', 'active' => 0],
            3 => ['id' => 3, 'code' => 13, 'country' => 'Latvia', 'active' => 1],
            4 => ['id' => 4, 'name' => 'ABC6', 'code' => 14, 'country' => 'UK', 'active' => 0],
            5 => ['id' => 5, 'name' => 'ABC5', 'code' => 15, 'country' => 'UK', 'active' => 0],
            6 => ['id' => 6, 'name' => 'ABC4', 'code' => 16, 'country' => 'Ireland', 'active' => 1],
            7 => ['id' => 7, 'name' => 'ABC3', 'code' => 17, 'country' => 'Latvia', 'active' => 0],
            8 => ['id' => 8, 'name' => 'ABC2', 'code' => 18, 'country' => 'Russia', 'active' => 1],
            9 => ['id' => 9, 'code' => 19, 'country' => 'Latvia', 'active' => 1],
        ]];

        $dbDataCountries = $dbData['countries'];
        foreach ($dbDataCountries as $k => $v) {
            $dbDataCountries[$k] = array_merge(['id' => $k], $v);
        }

        $p = new Persistence\Array_($dbData);
        $m = new Model($p, ['table' => 'countries']);
        $m->addField('code', ['type' => 'integer']);
        $m->addField('country');
        $m->addField('active', ['type' => 'boolean']);

        $m->scope()->clear();
        $m->addCondition('country', 'REGEXP', 'Ireland|UK');
        $result = $m->toQuery()->select()->getRows();
        $this->assertCount(5, $result);
        $this->assertSame($dbDataCountries[1], $result[1]);
        $this->assertSame($dbDataCountries[2], $result[2]);
        $this->assertSame($dbDataCountries[4], $result[4]);
        $this->assertSame($dbDataCountries[5], $result[5]);
        $this->assertSame($dbDataCountries[6], $result[6]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('country', 'NOT REGEXP', 'Ireland|UK|Latvia');
        $result = $m->toQuery()->select()->getRows();
        $this->assertCount(1, $result);
        $this->assertSame($dbDataCountries[8], $result[8]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', '>', 18);
        $result = $m->toQuery()->select()->getRows();
        $this->assertCount(1, $result);
        $this->assertSame($dbDataCountries[9], $result[9]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', '>=', 18);
        $result = $m->toQuery()->select()->getRows();
        $this->assertCount(2, $result);
        $this->assertSame($dbDataCountries[8], $result[8]);
        $this->assertSame($dbDataCountries[9], $result[9]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', '<', 12);
        $result = $m->toQuery()->select()->getRows();
        $this->assertCount(1, $result);
        $this->assertSame($dbDataCountries[1], $result[1]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', '<=', 12);
        $result = $m->toQuery()->select()->getRows();
        $this->assertCount(2, $result);
        $this->assertSame($dbDataCountries[1], $result[1]);
        $this->assertSame($dbDataCountries[2], $result[2]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', [11, 12]);
        $result = $m->toQuery()->select()->getRows();
        $this->assertCount(2, $result);
        $this->assertSame($dbDataCountries[1], $result[1]);
        $this->assertSame($dbDataCountries[2], $result[2]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', 'IN', []);
        $result = $m->toQuery()->select()->getRows();
        $this->assertCount(0, $result);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', 'NOT IN', [11, 12, 13, 14, 15, 16, 17]);
        $result = $m->toQuery()->select()->getRows();
        $this->assertCount(2, $result);
        $this->assertSame($dbDataCountries[8], $result[8]);
        $this->assertSame($dbDataCountries[9], $result[9]);
        unset($result);

        $m->scope()->clear();
        $m->addCondition('code', '!=', [11, 12, 13, 14, 15, 16, 17]);
        $result = $m->toQuery()->select()->getRows();
        $this->assertCount(2, $result);
        $this->assertSame($dbDataCountries[8], $result[8]);
        $this->assertSame($dbDataCountries[9], $result[9]);
        unset($result);
    }

    public function testAggregates(): void
    {
        $p = new Persistence\Array_(['invoices' => [
            1 => ['id' => 1, 'number' => 'ABC9', 'items' => 11, 'active' => 1],
            2 => ['id' => 2, 'number' => 'ABC8', 'items' => 12, 'active' => 0],
            3 => ['id' => 3, 'items' => 13, 'active' => 1],
            4 => ['id' => 4, 'number' => 'ABC6', 'items' => 14, 'active' => 0],
            5 => ['id' => 5, 'number' => 'ABC5', 'items' => 15, 'active' => 0],
            6 => ['id' => 6, 'number' => 'ABC4', 'items' => 16, 'active' => 1],
            7 => ['id' => 7, 'number' => 'ABC3', 'items' => 17, 'active' => 0],
            8 => ['id' => 8, 'number' => 'ABC2', 'items' => 18, 'active' => 1],
            9 => ['id' => 9, 'items' => 19, 'active' => 1],
            10 => ['id' => 10, 'items' => 0, 'active' => 1],
            11 => ['id' => 11, 'items' => null, 'active' => 1],
        ]]);
        $m = new Model($p, ['table' => 'invoices']);
        $m->addField('items', ['type' => 'integer']);

        $this->assertSame(13.5, $m->toQuery()->aggregate('avg', 'items')->getOne());
        $this->assertSame(12.272727272727273, $m->toQuery()->aggregate('avg', 'items', null, true)->getOne());
        $this->assertSame(0, $m->toQuery()->aggregate('min', 'items')->getOne());
        $this->assertSame(19, $m->toQuery()->aggregate('max', 'items')->getOne());
        $this->assertSame(135, $m->toQuery()->aggregate('sum', 'items')->getOne());
    }

    public function testExists(): void
    {
        $p = new Persistence\Array_(['invoices' => [
            1 => ['id' => 1, 'number' => 'ABC9', 'items' => 11, 'active' => 1],
        ]]);
        $m = new Model($p, ['table' => 'invoices']);
        $m->addField('items', ['type' => 'integer']);

        $this->assertSame(1, $m->toQuery()->exists()->getOne());

        $m->delete(1);

        $this->assertSame(0, $m->toQuery()->exists()->getOne());
    }

    /**
     * Returns exported data, but will use get() instead of export().
     */
    protected function _getRows(Model $model, array $fields = []): array
    {
        $d = [];
        foreach ($model as $row) {
            $rowData = $row->get();
            $rowData = $fields ? array_intersect_key($rowData, array_flip($fields)) : $rowData;
            $d[] = $rowData;
        }

        return $d;
    }

    /**
     * Test Model->setOrder().
     */
    public function testOrder(): void
    {
        $dbData = [
            1 => ['id' => 1, 'f1' => 'A', 'f2' => 'B'],
            2 => ['id' => 2, 'f1' => 'D', 'f2' => 'A'],
            3 => ['id' => 3, 'f1' => 'D', 'f2' => 'C'],
            4 => ['id' => 4, 'f1' => 'A', 'f2' => 'C'],
            5 => ['id' => 5, 'f1' => 'E', 'f2' => 'A'],
            6 => ['id' => 6, 'f1' => 'C', 'f2' => 'A'],
        ];

        // order by one field ascending
        $p = new Persistence\Array_($dbData);
        $m = new Model($p);
        $m->addField('f1');
        $m->addField('f2');
        $m->addField('f3');
        $m->setOrder('f1');
        $d = $this->_getRows($m, ['f1']);
        $this->assertSame([
            ['f1' => 'A'],
            ['f1' => 'A'],
            ['f1' => 'C'],
            ['f1' => 'D'],
            ['f1' => 'D'],
            ['f1' => 'E'],
        ], $d);
        $this->assertSame($d, array_values($m->export(['f1']))); // array_values to get rid of keys

        // order by one field descending
        $p = new Persistence\Array_($dbData);
        $m = new Model($p);
        $m->addField('f1');
        $m->addField('f2');
        $m->addField('f3');
        $m->setOrder('f1', 'desc');
        $d = $this->_getRows($m, ['f1']);
        $this->assertSame([
            ['f1' => 'E'],
            ['f1' => 'D'],
            ['f1' => 'D'],
            ['f1' => 'C'],
            ['f1' => 'A'],
            ['f1' => 'A'],
        ], $d);
        $this->assertSame($d, array_values($m->export(['f1']))); // array_values to get rid of keys

        // order by two fields ascending
        $p = new Persistence\Array_($dbData);
        $m = new Model($p);
        $m->addField('f1');
        $m->addField('f2');
        $m->addField('f3');

        $m->setOrder('f1', 'desc');
        $m->setOrder('f2', 'desc');
        $d = $this->_getRows($m, ['f1', 'f2', 'id']);
        $this->assertEquals([
            ['f1' => 'E', 'f2' => 'A', 'id' => 5],
            ['f1' => 'D', 'f2' => 'C', 'id' => 3],
            ['f1' => 'D', 'f2' => 'A', 'id' => 2],
            ['f1' => 'C', 'f2' => 'A', 'id' => 6],
            ['f1' => 'A', 'f2' => 'C', 'id' => 4],
            ['f1' => 'A', 'f2' => 'B', 'id' => 1],
        ], $d);
        $this->assertSame($d, array_values($m->export(['f1', 'f2', 'id']))); // array_values to get rid of keys
    }

    public function testNoKeyException(): void
    {
        $p = new Persistence\Array_([
            ['id' => 3, 'f1' => 'A'],
        ]);

        $m = new Model($p);
        $this->expectException(Exception::class);

        $m->export();
    }

    public function testImportAndAutoincrement(): void
    {
        $p = new Persistence\Array_([]);
        $m = new Model($p);
        $m->addField('f1');

        $m->import([
            ['id' => 1, 'f1' => 'A'],
            ['id' => 2, 'f1' => 'B'],
        ]);
        $this->assertSame(2, $m->getCount());

        $m->import([
            ['f1' => 'C'],
            ['f1' => 'D'],
        ]);
        $this->assertSame(4, $m->getCount());

        $m->import([
            ['id' => 6, 'f1' => 'E'],
            ['id' => 7, 'f1' => 'F'],
        ]);
        $this->assertSame(6, $m->getCount());

        $m->delete(6);
        $this->assertSame(5, $m->getCount());

        $m->import([
            ['f1' => 'G'],
            ['f1' => 'H'],
        ]);
        $this->assertSame(7, $m->getCount());

        $m->import([
            ['id' => 99, 'f1' => 'I'],
            ['id' => 20, 'f1' => 'J'],
        ]);
        $this->assertSame(9, $m->getCount());

        $m->import([
            ['f1' => 'K'],
        ]);
        $this->assertSame(10, $m->getCount());

        $this->assertSame([
            1 => ['id' => 1, 'f1' => 'A'],
            2 => ['id' => 2, 'f1' => 'B'],
            3 => ['id' => 3, 'f1' => 'C'],
            4 => ['id' => 4, 'f1' => 'D'],
            7 => ['id' => 7, 'f1' => 'F'],
            8 => ['id' => 8, 'f1' => 'G'],
            9 => ['id' => 9, 'f1' => 'H'],
            99 => ['id' => 99, 'f1' => 'I'],
            20 => ['id' => 20, 'f1' => 'J'],
            100 => ['id' => 100, 'f1' => 'K'],
        ], $m->export());
    }

    /**
     * Test Model->setLimit().
     */
    public function testLimit(): void
    {
        // order by one field ascending
        $p = new Persistence\Array_([
            ['f1' => 'A'],
            ['f1' => 'D'],
            ['f1' => 'E'],
            ['f1' => 'C'],
        ]);
        $m = new Model($p);
        $m->addField('f1');

        $this->assertSame(4, $m->getCount());

        $m->setLimit(3);
        $this->assertSame(3, $m->getCount());
        $this->assertSame([
            ['id' => 0, 'f1' => 'A'],
            ['id' => 1, 'f1' => 'D'],
            ['id' => 2, 'f1' => 'E'],
        ], array_values($m->export()));

        $m->setLimit(2, 1);
        $this->assertSame(2, $m->getCount());
        $this->assertSame([
            ['id' => 1, 'f1' => 'D'],
            ['id' => 2, 'f1' => 'E'],
        ], array_values($m->export()));

        // well, this is strange, that you can actually change limit on-the-fly and then previous
        // limit is not taken into account, but most likely you will never set it multiple times
        $m->setLimit(3);
        $this->assertSame(3, $m->getCount());
    }

    /**
     * Test Model->addCondition().
     */
    public function testCondition(): void
    {
        $p = new Persistence\Array_($dbData = [
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'QQ'],
            3 => ['name' => 'Sarah', 'surname' => 'XX'],
            4 => ['name' => 'Sarah', 'surname' => 'Smith'],
        ]);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $this->assertSame(4, $m->getCount());
        $this->assertSame(['data' => $dbData], $this->getInternalPersistenceData($p));

        $m->addCondition('name', 'Sarah');
        $this->assertSame(3, $m->getCount());

        $m->addCondition('surname', 'Smith');
        $this->assertSame(1, $m->getCount());
        $this->assertSame([4 => ['id' => 4, 'name' => 'Sarah', 'surname' => 'Smith']], $m->export());
        $this->assertSame([4 => ['id' => 4, 'name' => 'Sarah', 'surname' => 'Smith']], $m->toQuery()->select()->getRows());

        $m->addCondition('surname', 'Siiiith');
        $this->assertSame(0, $m->getCount());
    }

    public function testUnsupportedAggregate(): void
    {
        $p = new Persistence\Array_([1 => ['name' => 'John']]);
        $m = new Model($p);
        $m->addField('name');

        $this->expectException(Exception::class);
        $m->toQuery()->aggregate('UNSUPPORTED', 'name');
    }

    public function testUnsupportedCondition1(): void
    {
        $p = new Persistence\Array_([1 => ['name' => 'John']]);
        $m = new Model($p);
        $m->addField('name');
        $this->expectException(Exception::class);
        $m->addCondition('name');
    }

    public function testUnsupportedCondition2(): void
    {
        $p = new Persistence\Array_([1 => ['name' => 'John']]);
        $m = new Model($p);
        $m->addField('name');
        $this->expectException(Exception::class);
        $m->addCondition(new Model(), 'like', '%o%');
    }

    /**
     * Test Model->hasOne().
     */
    public function testHasOne(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'country_id' => 1],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'country_id' => 2],
            ],
            'country' => [
                1 => ['name' => 'Latvia'],
                2 => ['name' => 'UK'],
            ],
        ]);

        $user = new Model($p, ['table' => 'user']);
        $user->addField('name');
        $user->addField('surname');

        $country = new Model();
        $country->table = 'country';
        $country->addField('name');

        $user->hasOne('country', ['theirModel' => $country]);

        $uu = $user->load(1);
        $this->assertSame('Latvia', $uu->ref('country')->get('name'));

        $uu = $user->load(2);
        $this->assertSame('UK', $uu->ref('country')->get('name'));
    }

    /**
     * Test Model->hasMany().
     */
    public function testHasMany(): void
    {
        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'country_id' => 1],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'country_id' => 2],
                3 => ['name' => 'Janis', 'surname' => 'Berzins', 'country_id' => 1],
            ],
            'country' => [
                1 => ['name' => 'Latvia'],
                2 => ['name' => 'UK'],
            ],
        ]);

        $country = new Model($p, ['table' => 'country']);
        $country->addField('name');

        $user = new Model();
        $user->table = 'user';
        $user->addField('name');
        $user->addField('surname');

        $country->hasMany('Users', ['theirModel' => $user]);
        $user->hasOne('country', ['theirModel' => $country]);

        $cc = $country->load(1);
        $this->assertSame(2, $cc->ref('Users')->getCount());

        $cc = $country->load(2);
        $this->assertSame(1, $cc->ref('Users')->getCount());
    }

    public function testLoadAnyThrowsExceptionOnRecordNotFound(): void
    {
        $p = new Persistence\Array_();
        $m = new Model($p);
        $m->addField('name');
        $this->expectException(Model\RecordNotFoundException::class);
        $m = $m->loadAny();
    }

    public function testTryLoadAnyNotThrowsExceptionOnRecordNotFound(): void
    {
        $p = new Persistence\Array_();
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');
        $m = $m->tryLoadAny();
        $this->assertFalse($m->isLoaded());
    }

    public function testTryLoadAnyReturnsFirstRecord(): void
    {
        $a = [
            2 => ['name' => 'John', 'surname' => 'Smith'],
            3 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $p = new Persistence\Array_($a);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');
        $m = $m->tryLoadAny();
        $this->assertSame(2, $m->getId());
    }
}
