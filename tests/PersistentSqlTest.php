<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Model;
use Phlex\Data\Persistence;

class PersistentSqlTest extends Sql\TestCase
{
    public function testCodecResolution()
    {
        $persistence = (new \ReflectionClass(Persistence\Sql\Platform\Oracle::class))
            ->newInstanceWithoutConstructor();

        $this->assertSame([
            Model\Field\Type\Object_::class => [Persistence\Sql\Platform\Oracle\Codec\Object_::class],
            Model\Field\Type\Array_::class => [Persistence\Sql\Platform\Oracle\Codec\Array_::class],
            0 => [Persistence\Sql\Codec\String_::class],
            Model\Field\Type\Selectable::class => [Persistence\Sql\Codec\Selectable::class],
            Model\Field\Type\Boolean::class => [Persistence\Sql\Codec\Boolean::class],
            Model\Field\Type\Date::class => [Persistence\Sql\Codec\Date::class],
            Model\Field\Type\DateTime::class => [Persistence\Sql\Codec\DateTime::class],
            Model\Field\Type\Time::class => [Persistence\Sql\Codec\Time::class],
            Model\Field\Type\Float_::class => [Persistence\Sql\Codec\Float_::class],
            Model\Field\Type\Integer::class => [Persistence\Sql\Codec\Integer::class],
            Model\Field\Type\String_::class => [Persistence\Sql\Codec\String_::class],
            Model\Field\Type\Text::class => [Persistence\Sql\Codec\Text::class],
        ], $persistence->getCodecs());

        $persistence->setCodecs([
            Model\Field\Type\Object_::class => ['fake_class'],
        ]);

        $this->assertSame([
            Model\Field\Type\Object_::class => ['fake_class'],
            Model\Field\Type\Array_::class => [Persistence\Sql\Platform\Oracle\Codec\Array_::class],
            0 => [Persistence\Sql\Codec\String_::class],
            Model\Field\Type\Selectable::class => [Persistence\Sql\Codec\Selectable::class],
            Model\Field\Type\Boolean::class => [Persistence\Sql\Codec\Boolean::class],
            Model\Field\Type\Date::class => [Persistence\Sql\Codec\Date::class],
            Model\Field\Type\DateTime::class => [Persistence\Sql\Codec\DateTime::class],
            Model\Field\Type\Time::class => [Persistence\Sql\Codec\Time::class],
            Model\Field\Type\Float_::class => [Persistence\Sql\Codec\Float_::class],
            Model\Field\Type\Integer::class => [Persistence\Sql\Codec\Integer::class],
            Model\Field\Type\String_::class => [Persistence\Sql\Codec\String_::class],
            Model\Field\Type\Text::class => [Persistence\Sql\Codec\Text::class],
        ], $persistence->getCodecs());

        $model = new Model($persistence, ['table' => 'fake']);

        $field = $model->addField('array', ['type' => 'array']);

        $this->assertSame(Persistence\Sql\Platform\Oracle\Codec\Array_::class, get_class($field->getCodec()));
    }

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

//     public function testModelLoadOneAndAny(): void
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
//         $this->assertSame('John', (clone $mm)->loadAny()->get('name'));
//         $this->assertSame('John', (clone $mm)->tryLoadAny()->get('name'));
//         $this->assertSame('John', (clone $mm)->loadAny()->get('name'));

//         $mm = (clone $m)->addCondition('surname', 'Jones');
//         $this->assertSame('Sarah', (clone $mm)->load(2)->get('name'));
//         $this->assertNull((clone $mm)->tryload(1)->get('name'));
//         $this->assertSame('Sarah', (clone $mm)->tryLoadAny()->get('name'));
//         $this->assertSame('Sarah', (clone $mm)->loadAny()->get('name'));
//         $this->assertSame('Sarah', (clone $mm)->tryLoadAny()->get('name'));
//         $this->assertSame('Sarah', (clone $mm)->loadAny()->get('name'));

//         (clone $m)->loadAny();
//         (clone $m)->tryLoadAny();
//         $this->expectException(Exception::class);
//         $this->expectExceptionMessage('Ambiguous conditions, more than one record can be loaded.');
//         (clone $m)->tryLoadAny();
//     }

    public function testPersistenceInsert(): void
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

        $mm = $m->load($ids[0]);
        $this->assertSame('John', $mm->get('name'));

        $mm = $m->load($ids[1]);
        $this->assertSame('Jones', $mm->get('surname'));
        $mm->set('surname', 'Smith');
        $mm->save();

        $mm = $m->load($ids[0]);
        $this->assertSame('John', $mm->get('name'));

        $mm = $m->load($ids[1]);
        $this->assertSame('Smith', $mm->get('surname'));
    }

    public function testModelInsert(): void
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

        $this->assertSame('John', $m->load($ms[0])->get('name'));

        $this->assertSame('Jones', $m->load($ms[1])->get('surname'));
    }

    public function testModelSaveNoReload(): void
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
        $m->reloadAfterSave = false;
        $m = $m->createEntity();
        $m->save(['name' => 'Jane', 'surname' => 'Doe']);
        $this->assertSame('Jane', $m->get('name'));
        $this->assertSame('Doe', $m->get('surname'));
        $this->assertEquals(3, $m->getId());
        // id field value is set with new id value even if reload_after_save = false
        $this->assertEquals(3, $m->getId());
    }

    public function testModelInsertRows(): void
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

        $this->assertEquals(2, $m->getCount());
    }

    public function testPersistenceDelete(): void
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

        $m->delete($ids[0]);

        $m2 = $m->load($ids[1]);
        $this->assertSame('Jones', $m2->get('surname'));
        $m2->set('surname', 'Smith');
        $m2->save();

        $m2 = $m->tryLoad($ids[0]);
        $this->assertFalse($m2->isLoaded());

        $m2 = $m->load($ids[1]);
        $this->assertSame('Smith', $m2->get('surname'));
    }

    /**
     * Test export.
     */
    public function testExport(): void
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
