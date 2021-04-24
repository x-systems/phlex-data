<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Exception;
use Phlex\Data\Model;

class PersistentSqlTest extends SQL\TestCase
{
    /**
     * Test constructor.
     */
    public function testLoadArray()
    {
        $this->setDb([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $mm = (clone $m)->load(1);
        $this->assertSame('John', $mm->get('name'));

        $mm = (clone $m)->load(2);
        $this->assertSame('Jones', $mm->get('surname'));
        $mm->set('surname', 'Smith');
        $mm->save();

        $mm = (clone $m)->load(1);
        $this->assertSame('John', $mm->get('name'));

        $mm = (clone $m)->load(2);
        $this->assertSame('Smith', $mm->get('surname'));
    }

//     public function testModelLoadOneAndAny()
//     {
//         $this->setDb([
//             'user' => [
//                 1 => ['name' => 'John', 'surname' => 'Smith'],
//                 2 => ['name' => 'Sarah', 'surname' => 'Jones'],
//             ],
//         ]);

//         $m = new Model($this->db, ['table' => 'user']);
//         $m->addField('name');
//         $m->addField('surname');

//         $mm = (clone $m)->addCondition($m->primaryKey, 1);
//         $this->assertSame('John', (clone $mm)->load(1)->get('name'));
//         $this->assertNull((clone $mm)->tryload(2)->get('name'));
//         $this->assertSame('John', (clone $mm)->tryLoadOne()->get('name'));
//         $this->assertSame('John', (clone $mm)->loadOne()->get('name'));
//         $this->assertSame('John', (clone $mm)->tryLoadAny()->get('name'));
//         $this->assertSame('John', (clone $mm)->loadAny()->get('name'));

//         $mm = (clone $m)->addCondition('surname', 'Jones');
//         $this->assertSame('Sarah', (clone $mm)->load(2)->get('name'));
//         $this->assertNull((clone $mm)->tryload(1)->get('name'));
//         $this->assertSame('Sarah', (clone $mm)->tryloadOne()->get('name'));
//         $this->assertSame('Sarah', (clone $mm)->loadOne()->get('name'));
//         $this->assertSame('Sarah', (clone $mm)->tryLoadAny()->get('name'));
//         $this->assertSame('Sarah', (clone $mm)->loadAny()->get('name'));

//         (clone $m)->loadAny();
//         (clone $m)->tryLoadAny();
//         $this->expectException(Exception::class);
//         $this->expectExceptionMessage('Ambiguous conditions, more than one record can be loaded.');
//         (clone $m)->tryLoadOne();
//     }

    public function testPersistenceInsert()
    {
        $dbData = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];

        $this->setDb($dbData);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $ids = [];
        foreach ($dbData['user'] as $id => $row) {
            $ids[] = $this->db->insert($m, $row);
        }

        $mm = (clone $m)->load($ids[0]);
        $this->assertSame('John', $mm->get('name'));

        $mm = (clone $m)->load($ids[1]);
        $this->assertSame('Jones', $mm->get('surname'));
        $mm->set('surname', 'Smith');
        $mm->save();

        $mm = (clone $m)->load($ids[0]);
        $this->assertSame('John', $mm->get('name'));

        $mm = (clone $m)->load($ids[1]);
        $this->assertSame('Smith', $mm->get('surname'));
    }

    public function testModelInsert()
    {
        $dbData = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];
        $this->setDb($dbData);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $ms = [];
        foreach ($dbData['user'] as $id => $row) {
            $ms[] = $m->insert($row);
        }

        $this->assertSame('John', (clone $m)->load($ms[0])->get('name'));

        $this->assertSame('Jones', (clone $m)->load($ms[1])->get('surname'));
    }

    public function testModelSaveNoReload()
    {
        $this->setDb([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        // insert new record, model id field
        $m->reload_after_save = false;
        $m->save(['name' => 'Jane', 'surname' => 'Doe']);
        $this->assertSame('Jane', $m->get('name'));
        $this->assertSame('Doe', $m->get('surname'));
        $this->assertEquals(3, $m->getId());
        // id field value is set with new id value even if reload_after_save = false
        $this->assertEquals(3, $m->getId());
    }

    public function testModelInsertRows()
    {
        $dbData = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];
        $this->setDb($dbData, false); // create empty table

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $this->assertEquals(0, $m->toQuery()->exists()->getOne());

        $m->import($dbData['user']); // import data

        $this->assertEquals(1, $m->toQuery()->exists()->getOne());

        $this->assertEquals(2, $m->toQuery()->count()->getOne());
    }

    public function testPersistenceDelete()
    {
        $dbData = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];
        $this->setDb($dbData);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $ids = [];
        foreach ($dbData['user'] as $id => $row) {
            $ids[] = $this->db->insert($m, $row);
        }
        $this->assertFalse($m->loaded());

        $m->delete($ids[0]);
        $this->assertFalse($m->loaded());

        $m->load($ids[1]);
        $this->assertSame('Jones', $m->get('surname'));
        $m->set('surname', 'Smith');
        $m->save();

        $m->tryLoad($ids[0]);
        $this->assertFalse($m->loaded());

        $m->load($ids[1]);
        $this->assertSame('Smith', $m->get('surname'));
    }

    /**
     * Test export.
     */
    public function testExport()
    {
        $this->setDb([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');

        $this->assertEquals([
            ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ['id' => 2, 'name' => 'Sarah', 'surname' => 'Jones'],
        ], $m->export());

        $this->assertSame([
            ['surname' => 'Smith'],
            ['surname' => 'Jones'],
        ], $m->export(['surname']));
    }
}
