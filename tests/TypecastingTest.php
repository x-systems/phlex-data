<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Doctrine\DBAL\Platforms\OraclePlatform;
use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

class MyDate extends \DateTime
{
    public function __toString()
    {
        return $this->format('Y-m-d');
    }
}

class MyTime extends \DateTime
{
    public function __toString()
    {
        return $this->format('H:i:s.u');
    }
}

class MyDateTime extends \DateTime
{
    public function __toString()
    {
        return $this->format('Y-m-d H:i:s.u');
    }
}

class TypecastingTest extends Sql\TestCase
{
    /** @var string */
    private $defaultTzBackup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultTzBackup = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTzBackup);

        parent::tearDown();
    }

    public function testType(): void
    {
        $dbData = [
            'types' => [
                [
                    'string' => 'foo',
                    'date' => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12.000000',
                    'time' => '12:00:50.000000',
                    'boolean' => 1,
                    'integer' => '2940',
                    'money' => '8.20',
                    'float' => '8.202343',
                    'array' => '[1,2,3]',
                ],
            ],
        ];
        $this->setDb($dbData);

        date_default_timezone_set('Asia/Seoul');

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('string', ['type' => 'string']);
        $m->addField('date', ['type' => 'date']);
        $m->addField('datetime', ['type' => 'datetime']);
        $m->addField('time', ['type' => 'time']);
        $m->addField('boolean', ['type' => 'boolean']);
        $m->addField('money', ['type' => 'money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('integer', ['type' => 'integer']);
        $m->addField('array', ['type' => 'array']);
        $mm = $m->load(1);

        $this->assertSame('foo', $mm->get('string'));
        $this->assertTrue($mm->get('boolean'));
        $this->assertSame(8.20, $mm->get('money'));
        $this->assertEquals(new \DateTime('2013-02-20'), $mm->get('date'));
        $this->assertEquals(new \DateTime('2013-02-20 20:00:12 UTC'), $mm->get('datetime'));
        $this->assertEquals(new \DateTime('1970-01-01 12:00:50'), $mm->get('time'));
        $this->assertSame(2940, $mm->get('integer'));
        $this->assertSame([1, 2, 3], $mm->get('array'));
        $this->assertSame(8.202343, $mm->get('float'));

        $values = $mm->get();
        unset($values['id']);
        $m->createEntity()->setMulti($values)->save();

        $dbData = [
            'types' => [
                1 => [
                    'id' => '1',
                    'string' => 'foo',
                    'date' => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12.000000',
                    'time' => '12:00:50.000000',
                    'boolean' => 1,
                    'integer' => 2940,
                    'money' => 8.20,
                    'float' => 8.202343,
                    'array' => '[1,2,3]',
                ],
                2 => [
                    'id' => '2',
                    'string' => 'foo',
                    'date' => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12.000000',
                    'time' => '12:00:50.000000',
                    'boolean' => '1',
                    'integer' => '2940',
                    'money' => '8.2',
                    'float' => '8.202343',
                    'array' => '[1,2,3]',
                ],
            ],
        ];
        $this->assertEquals($dbData, $this->getDb());

        [$first, $duplicate] = $m->export();

        unset($first['id']);
        unset($duplicate['id']);

        $this->assertEquals($first, $duplicate);
    }

    public function testEmptyValues(): void
    {
        // Oracle always converts empty string to null
        // see https://stackoverflow.com/questions/13278773/null-vs-empty-string-in-oracle#13278879
        $emptyStringValue = $this->getDatabasePlatform() instanceof OraclePlatform ? null : '';

        $dbData = [
            'types' => [
                1 => $row = [
                    'id' => 1,
                    'string' => '',
                    'notype' => '',
                    'date' => '',
                    'datetime' => '',
                    'time' => '',
                    'boolean' => '',
                    'integer' => '',
                    'money' => '',
                    'float' => '',
                    'array' => '',
                    'object' => '',
                ],
            ],
        ];
        $this->setDb($dbData);

        date_default_timezone_set('Asia/Seoul');

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('string', ['type' => 'string']);
        $m->addField('notype');
        $m->addField('date', ['type' => 'date']);
        $m->addField('datetime', ['type' => 'datetime']);
        $m->addField('time', ['type' => 'time']);
        $m->addField('boolean', ['type' => 'boolean']);
        $m->addField('integer', ['type' => 'integer']);
        $m->addField('money', ['type' => 'money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('array', ['type' => 'array']);
        $m->addField('object', ['type' => 'object']);
        $mm = $m->load(1);

        // Only
        $this->assertSame($emptyStringValue, $mm->get('string'));
        $this->assertSame($emptyStringValue, $mm->get('notype'));
        $this->assertNull($mm->get('date'));
        $this->assertNull($mm->get('datetime'));
        $this->assertNull($mm->get('time'));
        $this->assertNull($mm->get('boolean'));
        $this->assertNull($mm->get('integer'));
        $this->assertNull($mm->get('money'));
        $this->assertNull($mm->get('float'));
        $this->assertNull($mm->get('array'));
        $this->assertNull($mm->get('object'));

        unset($row['id']);
        $mm->setMulti($row);

        $this->assertSame('', $mm->get('string'));
        $this->assertSame('', $mm->get('notype'));
        $this->assertNull($mm->get('date'));
        $this->assertNull($mm->get('datetime'));
        $this->assertNull($mm->get('time'));
        $this->assertNull($mm->get('boolean'));
        $this->assertNull($mm->get('integer'));
        $this->assertNull($mm->get('money'));
        $this->assertNull($mm->get('float'));
        $this->assertNull($mm->get('array'));
        $this->assertNull($mm->get('object'));
        if (!$this->getDatabasePlatform() instanceof OraclePlatform) { // @TODO IMPORTANT we probably want to cast to string for Oracle on our own, so dirty array stay clean!
            $this->assertSame([], $mm->getEntry()->getDirty());
        }

        $mm->save();
        $this->assertEquals($dbData, $this->getDb());

        $m->createEntity()->setMulti(array_diff_key($mm->get(), ['id' => true]))->save();

        $dbData['types'][2] = [
            'id' => 2,
            'string' => $emptyStringValue,
            'notype' => $emptyStringValue,
            'date' => null,
            'datetime' => null,
            'time' => null,
            'boolean' => null,
            'integer' => null,
            'money' => null,
            'float' => null,
            'array' => null,
            'object' => null,
        ];

        $this->assertEquals($dbData, $this->getDb());
    }

    public function testTypecastNull(): void
    {
        $dbData = [
            'test' => [
                1 => $row = ['id' => '1', 'a' => 1, 'b' => '', 'c' => null],
            ],
        ];
        $this->setDb($dbData);

        $m = new Model($this->db, ['table' => 'test']);
        $m->addField('a');
        $m->addField('b');
        $m->addField('c');
        $m = $m->createEntity();

        unset($row['id']);
        $m->setMulti($row);
        $m->save();

        $dbData['test'][2] = array_merge(['id' => '2'], $row);

        $this->assertEquals($dbData, $this->getDb());
    }

    public function testTypeCustom1(): void
    {
        $dbData = [
            'types' => [
                $row = [
                    'date' => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12.235689',
                    'time' => '12:00:50.235689',
                    'integer' => '2940',
                    'money' => '8.20',
                    'float' => '8.202343',
                    'rot13' => 'uryyb jbeyq',
                ],
            ],
        ];
        $this->setDb($dbData);

        date_default_timezone_set('Asia/Seoul');

        $m = new Model($this->db, ['table' => 'types']);

        $m->addField('date', ['type' => ['date', 'dateTimeClass' => MyDate::class]]);
        $m->addField('datetime', ['type' => ['datetime', 'dateTimeClass' => MyDateTime::class]]);
        $m->addField('time', ['type' => ['time', 'dateTimeClass' => MyTime::class]]);
        $m->addField('money', ['type' => 'money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('integer', ['type' => 'integer']);

        $rot = fn ($v) => str_rot13($v);

        $m->addField('rot13', ['type' => ['string', 'codec' => [Persistence\Sql\Codec\Dynamic::class, 'encodeFx' => $rot, 'decodeFx' => $rot]]]);

        $mm = $m->load(1);

        $this->assertSame('hello world', $mm->get('rot13'));
        $this->assertSame(1, (int) $mm->getId());
        $this->assertSame(1, (int) $mm->get('id'));
        $this->assertSame('2013-02-21 05:00:12.235689', (string) $mm->get('datetime'));
        $this->assertSame('2013-02-20', (string) $mm->get('date'));
        $this->assertSame('12:00:50.235689', (string) $mm->get('time'));

        $m->createEntity()->setMulti(array_diff_key($mm->get(), ['id' => true]))->save();
        $m->delete(1);

        unset($dbData['types'][0]);
        $row['money'] = '8.2'; // here it will loose last zero and that's as expected
        $dbData['types'][2] = array_merge(['id' => '2'], $row);

        $this->assertEquals($dbData, $this->getDb());
    }

    public function testTryLoad(): void
    {
        $this->setDb([
            'types' => [
                [
                    'date' => '2013-02-20',
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);

        $m->addField('date', ['type' => ['date', 'dateTimeClass' => MyDate::class]]);

        $m = $m->tryLoad(1);

        $this->assertTrue($m->get('date') instanceof MyDate);
    }

    public function testTryLoadAny(): void
    {
        $this->setDb([
            'types' => [
                [
                    'date' => '2013-02-20',
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);

        $m->addField('date', ['type' => ['date', 'dateTimeClass' => MyDate::class]]);

        $m = $m->tryLoadAny();

        $this->assertTrue($m->get('date') instanceof MyDate);
    }

    public function testTryLoadBy(): void
    {
        $this->setDb([
            'types' => [
                [
                    'date' => '2013-02-20',
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);

        $m->addField('date', ['type' => ['date', 'dateTimeClass' => MyDate::class]]);

        $m = $m->loadBy('id', 1);

        $this->assertTrue($m->get('date') instanceof MyDate);
    }

    public function testLoadBy(): void
    {
        $this->setDb([
            'types' => [
                [
                    'date' => '2013-02-20',
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('date', ['type' => ['date', 'dateTimeClass' => MyDate::class]]);

        $m2 = $m->loadAny();
        $this->assertTrue($m2->isLoaded());
        $d = $m2->get('date');
        $m2->unload();

        $m2 = $m->loadBy('date', $d);
        $this->assertTrue($m2->isLoaded());
        $m2->unload();

        $m2 = $m->addCondition('date', $d)->loadAny();
        $this->assertTrue($m2->isLoaded());
    }

    public function testTypecastTimezone()
    {
        $m = new Model($this->db, ['table' => 'event']);
        $dt = $m->addField('dt', ['type' => ['datetime', 'codec' => ['timezone' => 'Europe/Vilnius']]]);
        $d = $m->addField('d', ['type' => ['date', 'codec' => ['timezone' => 'Europe/Vilnius']]]);
        $t = $m->addField('t', ['type' => ['time', 'codec' => ['timezone' => 'Europe/Vilnius']]]);

        date_default_timezone_set('UTC');
        $s = new \DateTime('Monday, 15-Aug-05 22:52:01 UTC');
        $this->assertSame('2005-08-16 01:52:01.000000', $dt->getCodec()->encode($s));
        $this->assertSame('2005-08-15', $d->getCodec()->encode($s));
        $this->assertSame('22:52:01.000000', $t->getCodec()->encode($s));
        $this->assertEquals(new \DateTime('Monday, 15-Aug-05 22:52:01 UTC'), $dt->getCodec()->decode('2005-08-16 01:52:01'));
        $this->assertEquals(new \DateTime('Monday, 15-Aug-05'), $d->getCodec()->decode('2005-08-15'));
        $this->assertEquals(new \DateTime('1970-01-01 22:52:01'), $t->getCodec()->decode('22:52:01'));

        date_default_timezone_set('Asia/Tokyo');

        $s = new \DateTime('Monday, 15-Aug-05 22:52:01 UTC');
        $this->assertSame('2005-08-16 01:52:01.000000', $dt->getCodec()->encode($s));
        $this->assertSame('2005-08-15', $d->getCodec()->encode($s));
        $this->assertSame('22:52:01.000000', $t->getCodec()->encode($s));
        $this->assertEquals(new \DateTime('Monday, 15-Aug-05 22:52:01 UTC'), $dt->getCodec()->decode('2005-08-16 01:52:01'));
        $this->assertEquals(new \DateTime('Monday, 15-Aug-05'), $d->getCodec()->decode('2005-08-15'));
        $this->assertEquals(new \DateTime('1970-01-01 22:52:01'), $t->getCodec()->decode('22:52:01'));

        date_default_timezone_set('America/Los_Angeles');

        $s = new \DateTime('Monday, 15-Aug-05 22:52:01'); // uses servers default timezone
        $this->assertSame('2005-08-16 08:52:01.000000', $dt->getCodec()->encode($s));
        $this->assertSame('2005-08-15', $d->getCodec()->encode($s));
        $this->assertSame('22:52:01.000000', $t->getCodec()->encode($s));
        $this->assertEquals(new \DateTime('Monday, 15-Aug-05 22:52:01 America/Los_Angeles'), $dt->getCodec()->decode('2005-08-16 08:52:01'));
        $this->assertEquals(new \DateTime('Monday, 15-Aug-05'), $d->getCodec()->decode('2005-08-15'));
        $this->assertEquals(new \DateTime('1970-01-01 22:52:01'), $t->getCodec()->decode('22:52:01'));
    }

    public function testTimestamp()
    {
        $sql_time = '2016-10-25 11:44:08';

        $this->setDb([
            'types' => [
                [
                    'date' => $sql_time,
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'datetime']);
        $m = $m->loadAny();

        // must respect 'actual'
        $this->assertNotNull($m->get('ts'));
    }

    public function testBadTimestamp(): void
    {
        $sql_time = '20blah16-10-25 11:44:08';

        $this->setDb([
            'types' => [
                [
                    'date' => $sql_time,
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'datetime']);
        $this->expectException(Exception::class);
        $m = $m->loadAny();
    }

    public function testDirtyTimestamp(): void
    {
        $sql_time = '2016-10-25 11:44:08';

        $this->setDb([
            'types' => [
                [
                    'date' => $sql_time,
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'datetime']);
        $m = $m->loadAny();

        $m->set('ts', clone $m->get('ts'));

        $this->assertFalse($m->isDirty('ts'));
    }

    public function testTimestampSave(): void
    {
        $this->setDb([
            'types' => [
                [
                    'date' => '',
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'date']);
        $m = $m->loadAny();
        $m->set('ts', new \DateTime('2012-02-30'));
        $m->save();

        // stores valid date.
        $this->assertEquals(['types' => [1 => ['id' => 1, 'date' => '2012-03-01']]], $this->getDb());
    }

    public function testIntegerSave(): void
    {
        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('i', ['type' => 'integer']);
        $m = $m->createEntity(['i' => 1]);

        $this->assertSame([], $m->getEntry()->getDirty());

        $m->set('i', '1');
        $this->assertSame([], $m->getEntry()->getDirty());

        $m->set('i', '2');
        $this->assertSame(['i' => 2], $m->getEntry()->getDirty());

        $m->set('i', '1');
        $this->assertSame([], $m->getEntry()->getDirty());

        // same test without type integer
        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('i');
        $m = $m->createEntity(['i' => 1]);

        $this->assertSame([], $m->getEntry()->getDirty());

        $m->set('i', '1');
        $this->assertSame([], $m->getEntry()->getDirty());

        $m->set('i', '2');
        $this->assertSame(['i' => '2'], $m->getEntry()->getDirty());

        $m->set('i', '1');
        $this->assertSame([], $m->getEntry()->getDirty());

        $m->set('i', 1);
        $this->assertSame([], $m->getEntry()->getDirty());
    }

    public function testDirtyTime(): void
    {
        $sql_time = '11:44:08';
        $sql_time_new = '12:34:56';

        $this->setDb([
            'types' => [
                [
                    'date' => $sql_time,
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'time']);
        $m = $m->loadAny();

        $m->set('ts', $sql_time_new);
        $this->assertTrue($m->isDirty('ts'));

        $m->set('ts', $sql_time);
        $this->assertFalse($m->isDirty('ts'));

        $m->set('ts', $sql_time_new);
        $this->assertTrue($m->isDirty('ts'));
    }

    public function testDirtyTimeAfterSave(): void
    {
        $sql_time = '11:44:08';
        $sql_time_new = '12:34:56';

        $this->setDb([
            'types' => [
                [
                    'date' => null,
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'time']);
        $m = $m->loadAny();

        $m->set('ts', $sql_time);
        $this->assertTrue($m->isDirty('ts'));

        $m->save();
        $m->reload();

        $m->set('ts', $sql_time);
        $this->assertFalse($m->isDirty('ts'));

        $m->set('ts', $sql_time_new);
        $this->assertTrue($m->isDirty('ts'));
    }
}
