<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

class FieldTest extends SQL\TestCase
{
    public function testExplicitPrimaryKey()
    {
        $m = new Model();
        $m->addField('primary_key')->asPrimaryKey();

        $this->assertSame('primary_key', $m->primaryKey);
        $this->assertTrue($m->getPrimaryKeyField()->required);
        $this->assertTrue($m->getPrimaryKeyField()->system);
    }

    public function testSecondPrimaryKeyException()
    {
        $m = new Model();
        $m->addField('primary_key1')->asPrimaryKey();

        $this->expectException(Exception::class);
        $m->addField('primary_key2')->asPrimaryKey();
    }

    public function testIsPrimaryKey()
    {
        $m = new Model();
        $m->addField('primary_key')->asPrimaryKey();
        $m->addField('normal');

        $this->assertTrue($m->getField('primary_key')->isPrimaryKey());
        $this->assertFalse($m->getField('normal')->isPrimaryKey());
    }

    public function testDirty1()
    {
        $m = new Model();
        $m->addField('foo', ['default' => 'abc']);

        $this->assertFalse($m->isDirty('foo'));

        $m->set('foo', 'abc');
        $this->assertFalse($m->isDirty('foo'));

        $m->set('foo', 'bca');
        $this->assertTrue($m->isDirty('foo'));

        $m->set('foo', 'abc');
        $this->assertFalse($m->isDirty('foo'));

        // set initial data
        $m->data['foo'] = 'xx';
        $this->assertFalse($m->isDirty('foo'));

        $m->set('foo', 'abc');
        $this->assertTrue($m->isDirty('foo'));

        $m->set('foo', 'bca');
        $this->assertTrue($m->isDirty('foo'));

        $m->set('foo', 'xx');
        $this->assertFalse($m->isDirty('foo'));
    }

    public function testCompare()
    {
        $m = new Model();
        $m->addField('foo', ['default' => 'abc']);

        $this->assertTrue($m->compare('foo', 'abc'));
        $m->set('foo', 'zzz');

        $this->assertFalse($m->compare('foo', 'abc'));
        $this->assertTrue($m->compare('foo', 'zzz'));
    }

    public function testMandatory1()
    {
        $m = new Model();
        $m->addField('foo', ['mandatory' => true]);
        $m->set('foo', 'abc');
        $m->set('foo', '');

        /* known bug, see https://github.com/x-systems/phlex-data/issues/575, fix in https://github.com/x-systems/phlex-data/issues/576
        $this->expectException(Model\Field\ValidationException::class);*/
        $m->set('foo', null);

        $this->assertTrue(true); // no exceptions
    }

    public function testRequired1()
    {
        $m = new Model();
        $m->addField('foo', ['required' => true]);

        $this->expectException(Model\Field\ValidationException::class);
        $m->set('foo', '');
    }

    public function testRequired11()
    {
        $m = new Model();
        $m->addField('foo', ['required' => true]);

        $this->expectException(Model\Field\ValidationException::class);
        $m->set('foo', null);
    }

    public function testMandatory2()
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ],
        ]);

        $m = new Model($db, ['table' => 'user']);
        $m->addField('name', ['mandatory' => true]);
        $m->addField('surname');
        $this->expectException(Exception::class);
        $m->insert(['surname' => 'qq']);
    }

    public function testRequired2()
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ],
        ]);

        $m = new Model($db, ['table' => 'user']);
        $m->addField('name', ['required' => true]);
        $m->addField('surname');
        $this->expectException(Exception::class);
        $m->insert(['surname' => 'qq', 'name' => '']);
    }

    public function testMandatory3()
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ],
        ]);

        $m = new Model($db, ['table' => 'user']);
        $m->addField('name', ['mandatory' => true]);
        $m->addField('surname');
        $m->load(1);
        $this->expectException(Exception::class);
        $m->save(['name' => null]);
    }

    public function testMandatory4()
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ],
        ]);

        $m = new Model($db, ['table' => 'user']);
        $m->addField('name', ['mandatory' => true, 'default' => 'NoName']);
        $m->addField('surname');
        $m->insert(['surname' => 'qq']);
        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
                2 => ['id' => 2, 'name' => 'NoName', 'surname' => 'qq'],
            ],
        ], $this->getDb());
    }

    public function testCaption()
    {
        $m = new Model();
        $f = $m->addField('foo');
        $this->assertSame('Foo', $f->getCaption());

        $f = $m->addField('user_defined_entity');
        $this->assertSame('User Defined Entity', $f->getCaption());

        $f = $m->addField('foo2', ['caption' => 'My Foo']);
        $this->assertSame('My Foo', $f->getCaption());

        $f = $m->addField('foo3', ['ui' => ['caption' => 'My Foo']]);
        $this->assertSame('My Foo', $f->getCaption());

        $f = $m->addField('userDefinedEntity');
        $this->assertSame('User Defined Entity', $f->getCaption());

        $f = $m->addField('newNASA_module');
        $this->assertSame('New NASA Module', $f->getCaption());

        $f = $m->addField('this\\ _isNASA_MyBigBull shit_123\Foo');
        $this->assertSame('This Is NASA My Big Bull Shit 123 Foo', $f->getCaption());
    }

    public function testReadOnly1()
    {
        $m = new Model();
        $m->addField('foo', ['access' => Model\Field::ACCESS_GET]);
        $this->expectException(Exception::class);
        $m->set('foo', 'bar');
    }

    public function testReadOnly2()
    {
        $m = new Model();
        $m->addField('foo', ['read_only' => true, 'default' => 'abc']);
        $m->set('foo', 'abc');
        $this->assertSame('abc', $m->get('foo'));
    }

    public function testEnum1()
    {
        $m = new Model();
        $m->addField('foo', ['enum' => ['foo', 'bar']]);
        $this->expectException(Exception::class);
        $m->set('foo', 'xx');
    }

    public function testEnum2()
    {
        $m = new Model();
        $m->addField('foo', ['enum' => [1, 'bar']]);
        $m->set('foo', 1);

        $this->assertSame(1, $m->get('foo'));

        $m->set('foo', 'bar');
        $this->assertSame('bar', $m->get('foo'));
    }

    public function testEnum3()
    {
        $m = new Model();
        $m->addField('foo', ['enum' => [1, 'bar']]);
        $this->expectException(Exception::class);
        $m->set('foo', true);
    }

    public function testEnum4()
    {
        // PHP type control is really crappy...
        // This test has no purpose but it stands testament
        // to a weird behaviours of PHP
        $m = new Model();
        $m->addField('foo', ['enum' => [1, 'bar'], 'default' => 1]);
        $m->set('foo', null);

        $this->assertNull($m->get('foo'));
    }

    public function testValues1()
    {
        $m = new Model();
        $m->addField('foo', ['values' => ['foo', 'bar']]);
        $this->expectException(Exception::class);
        $m->set('foo', 4);
    }

    public function testValues2()
    {
        $m = new Model();
        $m->addField('foo', ['values' => [3 => 'bar']]);
        $m->set('foo', 3);

        $this->assertSame(3, $m->get('foo'));

        $m->set('foo', null);
        $this->assertNull($m->get('foo'));
    }

    public function testValues3()
    {
        $m = new Model();
        $m->addField('foo', ['values' => [1 => 'bar']]);
        $this->expectException(Exception::class);
        $m->set('foo', true);
    }

    public function testValues3a()
    {
        $m = new Model();
        $m->addField('foo', ['values' => [1 => 'bar']]);
        $this->expectException(Exception::class);
        $m->set('foo', 'bar');
    }

    public function testValues4()
    {
        // PHP type control is really crappy...
        // This test has no purpose but it stands testament
        // to a weird behaviours of PHP
        $m = new Model();
        $m->addField('foo', ['values' => ['1a' => 'bar']]);
        $m->set('foo', '1a');
        $this->assertSame('1a', $m->get('foo'));
    }

    public function testPersist()
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb($dbData = [
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ],
        ]);

        $m = new Model($db, ['table' => 'item']);
        $m->addField('name', ['never_persist' => true]);
        $m->addField('surname', ['never_save' => true]);
        $m->load(1);

        $this->assertNull($m->get('name'));
        $this->assertSame('Smith', $m->get('surname'));

        $m->set('name', 'Bill');
        $m->set('surname', 'Stalker');
        $m->save();
        $this->assertEquals($dbData, $this->getDb());

        $m->reload();
        $this->assertSame('Smith', $m->get('surname'));
        $m->getField('surname')->setNeverSave(false);
        $m->set('surname', 'Stalker');
        $m->save();
        $dbData['item'][1]['surname'] = 'Stalker';
        $this->assertEquals($dbData, $this->getDb());

        $m->onHook(Model::HOOK_BEFORE_SAVE, static function ($m) {
            if ($m->isDirty('name')) {
                $m->set('surname', $m->get('name'));
                $m->_unset('name');
            } elseif ($m->isDirty('surname')) {
                $m->set('name', $m->get('surname'));
                $m->_unset('surname');
            }
        });

        $m->set('name', 'X');
        $m->save();

        $dbData['item'][1]['surname'] = 'X';

        $this->assertEquals($dbData, $this->getDb());
        $this->assertNull($m->get('name'));
        $this->assertSame('X', $m->get('surname'));

        $m->set('surname', 'Y');
        $m->save();

        $this->assertEquals($dbData, $this->getDb());
        $this->assertSame('Y', $m->get('name'));
        $this->assertSame('X', $m->get('surname'));
    }

    public function testTitle()
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'category_id' => 2],
            ],
            'category' => [
                1 => ['id' => 1, 'name' => 'General'],
                2 => ['id' => 2, 'name' => 'Programmer'],
                3 => ['id' => 3, 'name' => 'Sales'],
            ],
        ]);

        $c = new Model($db, ['table' => 'category']);
        $c->addField('name');

        $m = new Model($db, ['table' => 'user']);
        $m->addField('name');
        $m->hasOne('category_id', ['model' => $c])
            ->addTitle();

        $m->load(1);

        $this->assertSame('John', $m->get('name'));
        $this->assertSame('Programmer', $m->get('category'));

        $m->insert(['name' => 'Peter', 'category' => 'Sales']);

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'category_id' => 2],
                2 => ['id' => 2, 'name' => 'Peter', 'surname' => null, 'category_id' => 3],
            ],
            'category' => [
                1 => ['id' => 1, 'name' => 'General'],
                2 => ['id' => 2, 'name' => 'Programmer'],
                3 => ['id' => 3, 'name' => 'Sales'],
            ],
        ], $this->getDb());
    }

    public function testNonExisitngField()
    {
        $m = new Model();
        $m->addField('foo');
        $this->expectException(Exception::class);
        $m->set('baz', 'bar');
    }

    public function testActual()
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ],
        ]);

        $m = new Model($db, ['table' => 'user']);
        $m->addField('first_name', ['actual' => 'name']);
        $m->addField('surname');
        $m->insert(['first_name' => 'Peter', 'surname' => 'qq']);
        $m->loadBy('first_name', 'John');
        $this->assertSame('John', $m->get('first_name'));

        $d = $m->export();
        $this->assertSame('John', $d[0]['first_name']);

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
                2 => ['id' => 2, 'name' => 'Peter', 'surname' => 'qq'],
            ],
        ], $this->getDb());

        $m->set('first_name', 'Scott');
        $m->save();

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'Scott', 'surname' => 'Smith'],
                2 => ['id' => 2, 'name' => 'Peter', 'surname' => 'qq'],
            ],
        ], $this->getDb());
    }

    public function testCalculatedField()
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'invoice' => [
                1 => ['id' => 1, 'net' => 100, 'vat' => 21],
            ],
        ]);

        $m = new Model($db, ['table' => 'invoice']);
        $m->addField('net', ['type' => 'money']);
        $m->addField('vat', ['type' => 'money']);
        $m->addCalculatedField('total', function ($m) {
            return $m->get('net') + $m->get('vat');
        });
        $m->insert(['net' => 30, 'vat' => 8]);

        $mm = (clone $m)->load(1);
        $this->assertEquals(121, $mm->get('total'));
        $mm = (clone $m)->load(2);
        $this->assertEquals(38, $mm->get('total'));

        $d = $m->export(); // in export calculated fields are not included
        $this->assertFalse(isset($d[0]['total']));
    }

    public function testSystem1()
    {
        $m = new Model();
        $m->addField('foo', ['system' => true]);
        $m->addField('bar');
        $this->assertFalse($m->getField('foo')->isEditable());
        $this->assertFalse($m->getField('foo')->isVisible());

        $m->onlyFields(['bar']);
        // TODO: build a query and see if the field is there
    }

    public function testEncryptedField()
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'user' => [
                '_' => ['id' => 1, 'name' => 'John', 'secret' => 'Smith'],
            ],
        ]);

        $encrypt = function ($value, $field) {
            if (!$field->getOwner()->persistence instanceof Persistence\Sql) {
                return $value;
            }

            /*
            $algorithm = 'rijndael-128';
            $key = md5($field->password, true);
            $iv_length = mcrypt_get_iv_size( $algorithm, MCRYPT_MODE_CBC );
            $iv = mcrypt_create_iv( $iv_length, MCRYPT_RAND );
            return mcrypt_encrypt( $algorithm, $key, $value, MCRYPT_MODE_CBC, $iv );
             */
            return base64_encode($value);
        };

        $decrypt = function ($value, $field) {
            if (!$field->getOwner()->persistence instanceof Persistence\Sql) {
                return $value;
            }

            /*
            $algorithm = 'rijndael-128';
            $key = md5($field->password, true);
            $iv_length = mcrypt_get_iv_size( $algorithm, MCRYPT_MODE_CBC );
            $iv = mcrypt_create_iv( $iv_length, MCRYPT_RAND );
            return mcrypt_encrypt( $algorithm, $key, $value, MCRYPT_MODE_CBC, $iv );
             */
            return base64_decode($value, true);
        };

        $m = new Model($db, ['table' => 'user']);
        $m->addField('name', ['mandatory' => true]);
        $m->addField('secret', [
            //'password'  => 'bonkers',
            'type' => ['string', 'codec' => [Persistence\Sql\Codec\Dynamic::class, 'encodeFx' => $encrypt, 'decodeFx' => $decrypt]],
        ]);
        $m->save(['name' => 'John', 'secret' => 'i am a woman']);

        $dbData = $this->getDb();
        $this->assertNotNull($dbData['user'][1]['secret']);
        $this->assertNotSame('i am a woman', $dbData['user'][1]['secret']);

        $m->unload()->load(1);
        $this->assertSame('i am a woman', $m->get('secret'));
    }

    public function testNormalize()
    {
        $m = new Model(null, ['strict_types' => true]);

        // Field types: 'string', 'text', 'integer', 'money', 'float', 'boolean',
        //              'date', 'datetime', 'time', 'array', 'object'
        $m->addField('string', ['type' => 'string']);
        $m->addField('text', ['type' => 'text']);
        $m->addField('integer', ['type' => 'integer']);
        $m->addField('money', ['type' => 'money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('boolean', ['type' => 'boolean']);
        $m->addField('date', ['type' => 'date']);
        $m->addField('datetime', ['type' => 'datetime']);
        $m->addField('time', ['type' => 'time']);
        $m->addField('array', ['type' => 'array']);
        $m->addField('object', ['type' => 'object']);

        // string
        $m->set('string', "Two\r\nLines  ");
        $this->assertSame('TwoLines', $m->get('string'));

        $m->set('string', "Two\rLines  ");
        $this->assertSame('TwoLines', $m->get('string'));

        $m->set('string', "Two\nLines  ");
        $this->assertSame('TwoLines', $m->get('string'));

        // text
        $m->set('text', "Two\r\nLines  ");
        $this->assertSame("Two\nLines", $m->get('text'));

        $m->set('text', "Two\rLines  ");
        $this->assertSame("Two\nLines", $m->get('text'));

        $m->set('text', "Two\nLines  ");
        $this->assertSame("Two\nLines", $m->get('text'));

        // integer, money, float
        $m->set('integer', '12,345.67676767'); // no digits after dot
        $this->assertSame(12345, $m->get('integer'));

        $m->set('money', '12,345.67676767'); // 4 digits after dot
        $this->assertSame(12345.6768, $m->get('money'));

        $m->set('float', '12,345.67676767'); // don't round
        $this->assertSame(12345.67676767, $m->get('float'));

        // boolean
        $m->set('boolean', 0);
        $this->assertFalse($m->get('boolean'));
        $m->set('boolean', 1);
        $this->assertTrue($m->get('boolean'));

        // date, datetime, time
        $m->set('date', 123);
        $this->assertInstanceof('DateTime', $m->get('date'));
        $m->set('date', '123');
        $this->assertInstanceof('DateTime', $m->get('date'));
        $m->set('date', '2018-05-31');
        $this->assertInstanceof('DateTime', $m->get('date'));
        $m->set('datetime', 123);
        $this->assertInstanceof('DateTime', $m->get('datetime'));
        $m->set('datetime', '123');
        $this->assertInstanceof('DateTime', $m->get('datetime'));
        $m->set('datetime', '2018-05-31 12:13:14');
        $this->assertInstanceof('DateTime', $m->get('datetime'));
        $m->set('time', 123);
        $this->assertInstanceof('DateTime', $m->get('time'));
        $m->set('time', '123');
        $this->assertInstanceof('DateTime', $m->get('time'));
        $m->set('time', '12:13:14');
        $this->assertInstanceof('DateTime', $m->get('time'));
    }

    public function testNormalizeException1()
    {
        $m = new Model(null, ['strict_types' => true]);
        $m->addField('foo', ['type' => 'string']);
        $this->expectException(Model\Field\ValidationException::class);
        $m->set('foo', []);
    }

    public function testNormalizeException2()
    {
        $m = new Model(null, ['strict_types' => true]);
        $m->addField('foo', ['type' => 'text']);
        $this->expectException(Model\Field\ValidationException::class);
        $m->set('foo', []);
    }

    public function testNormalizeException3()
    {
        $m = new Model(null, ['strict_types' => true]);
        $m->addField('foo', ['type' => 'integer']);
        $this->expectException(Model\Field\ValidationException::class);
        $m->set('foo', []);
    }

    public function testNormalizeException4()
    {
        $m = new Model(null, ['strict_types' => true]);
        $m->addField('foo', ['type' => 'money']);
        $this->expectException(Model\Field\ValidationException::class);
        $m->set('foo', []);
    }

    public function testNormalizeException5()
    {
        $m = new Model(null, ['strict_types' => true]);
        $m->addField('foo', ['type' => 'float']);
        $this->expectException(Model\Field\ValidationException::class);
        $m->set('foo', []);
    }

    public function testNormalizeException6()
    {
        $m = new Model(null, ['strict_types' => true]);
        $m->addField('foo', ['type' => 'date']);
        $this->expectException(Model\Field\ValidationException::class);
        $m->set('foo', []);
    }

    public function testNormalizeException7()
    {
        $m = new Model(null, ['strict_types' => true]);
        $m->addField('foo', ['type' => 'datetime']);
        $this->expectException(Model\Field\ValidationException::class);
        $m->set('foo', []);
    }

    public function testNormalizeException8()
    {
        $m = new Model(null, ['strict_types' => true]);
        $m->addField('foo', ['type' => 'time']);
        $this->expectException(Model\Field\ValidationException::class);
        $m->set('foo', []);
    }

    public function testNormalizeException9()
    {
        $m = new Model(null, ['strict_types' => true]);
        $m->addField('foo', ['type' => 'integer']);
        $this->expectException(Model\Field\ValidationException::class);
        $m->set('foo', '123---456');
    }

    public function testNormalizeException10()
    {
        $m = new Model(null, ['strict_types' => true]);
        $m->addField('foo', ['type' => 'money']);
        $this->expectException(Model\Field\ValidationException::class);
        $m->set('foo', '123---456');
    }

    public function testNormalizeException11()
    {
        $m = new Model(null, ['strict_types' => true]);
        $m->addField('foo', ['type' => 'float']);
        $this->expectException(Model\Field\ValidationException::class);
        $m->set('foo', '123---456');
    }

    public function testNormalizeException12()
    {
        $m = new Model(null, ['strict_types' => true]);
        $m->addField('foo', ['type' => 'array']);
        $this->expectException(Model\Field\ValidationException::class);
        $m->set('foo', 'ABC');
    }

    public function testNormalizeException13()
    {
        $m = new Model(null, ['strict_types' => true]);
        $m->addField('foo', ['type' => 'object']);
        $this->expectException(Model\Field\ValidationException::class);
        $m->set('foo', 'ABC');
    }

    public function testNormalizeException14()
    {
        $m = new Model(null, ['strict_types' => true]);
        $m->addField('foo', ['type' => 'boolean']);
        $this->expectException(Model\Field\ValidationException::class);
        $m->set('foo', 'ABC');
    }

    public function testToString()
    {
        $m = new Model(null, ['strict_types' => true]);

        // Field types: 'string', 'text', 'integer', 'money', 'float', 'boolean',
        //              'date', 'datetime', 'time', 'array', 'object'
        $m->addField('string', ['type' => 'string']);
        $m->addField('text', ['type' => 'text']);
        $m->addField('integer', ['type' => 'integer']);
        $m->addField('money', ['type' => 'money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('boolean', ['type' => 'boolean']);
        $m->addField('date', ['type' => 'date']);
        $m->addField('datetime', ['type' => 'datetime']);
        $m->addField('time', ['type' => 'time']);
        $m->addField('array', ['type' => 'array']);
        $m->addField('object', ['type' => 'object']);

        $this->assertSame('TwoLines', $m->getField('string')->toString("Two\r\nLines  "));
        $this->assertSame("Two\nLines", $m->getField('text')->toString("Two\r\nLines  "));
        $this->assertSame('123', $m->getField('integer')->toString(123));
        $this->assertSame('123.45', $m->getField('money')->toString(123.45));
        $this->assertSame('123.456789', $m->getField('float')->toString(123.456789));
        $this->assertSame('1', $m->getField('boolean')->toString(true));
        $this->assertSame('0', $m->getField('boolean')->toString(false));
        $this->assertSame('2019-01-20', $m->getField('date')->toString(new \DateTime('2019-01-20T12:23:34+00:00')));
        $this->assertSame('2019-01-20T12:23:34+00:00', $m->getField('datetime')->toString(new \DateTime('2019-01-20T12:23:34+00:00')));
        $this->assertSame('12:23:34', $m->getField('time')->toString(new \DateTime('2019-01-20T12:23:34+00:00')));
        $this->assertSame('{"foo":"bar","int":123,"rows":["a","b"]}', $m->getField('array')->toString(['foo' => 'bar', 'int' => 123, 'rows' => ['a', 'b']]));
        $this->assertSame('{"foo":"bar","int":123,"rows":["a","b"]}', $m->getField('object')->toString((object) ['foo' => 'bar', 'int' => 123, 'rows' => ['a', 'b']]));
    }

    public function testAddFieldDirectly()
    {
        $this->expectException(Exception::class);
        $model = new Model();
        $model->add(new Model\Field(), ['test']);
    }

    public function testGetFields()
    {
        $model = new Model();
        $model->addField('system', ['system' => true]);
        $model->addField('editable', ['ui' => ['editable' => true]]);
        $model->addField('editable_system', ['ui' => ['editable' => true], 'system' => true]);
        $model->addField('visible', ['ui' => ['visible' => true]]);
        $model->addField('visible_system', ['ui' => ['visible' => true], 'system' => true]);
        $model->addField('not_editable', ['ui' => ['editable' => false]]);

        $this->assertSame(['system', 'editable', 'editable_system', 'visible', 'visible_system', 'not_editable'], array_keys($model->getFields()));
        $this->assertSame(['system', 'editable_system', 'visible_system'], array_keys($model->getFields(Model::FIELD_FILTER_SYSTEM)));
        $this->assertSame(['editable', 'visible', 'not_editable'], array_keys($model->getFields(Model::FIELD_FILTER_NOT_SYSTEM)));
        $this->assertSame(['editable', 'editable_system', 'visible'], array_keys($model->getFields(Model::FIELD_FILTER_EDITABLE)));
        $this->assertSame(['editable', 'visible', 'visible_system', 'not_editable'], array_keys($model->getFields(Model::FIELD_FILTER_VISIBLE)));
        $this->assertSame(['editable', 'editable_system', 'visible', 'visible_system', 'not_editable'], array_keys($model->getFields([Model::FIELD_FILTER_EDITABLE, Model::FIELD_FILTER_VISIBLE])));

        $model->onlyFields(['system', 'visible', 'not_editable']);

        // getFields() is unaffected by only_fields, will always return all fields
        $this->assertSame(['system', 'editable', 'editable_system', 'visible', 'visible_system', 'not_editable'], array_keys($model->getFields()));

        // only return subset of only_fields
        $this->assertSame(['visible', 'not_editable'], array_keys($model->getFields(Model::FIELD_FILTER_VISIBLE)));

        $this->expectExceptionMessage('not supported');
        $model->getFields('foo');
    }

    public function testDateTimeFieldsToString()
    {
        $model = new Model();
        $model->addField('date', ['type' => 'date']);
        $model->addField('time', ['type' => 'time']);
        $model->addField('datetime', ['type' => 'datetime']);

        $this->assertSame('', $model->getField('date')->toString());
        $this->assertSame('', $model->getField('time')->toString());
        $this->assertSame('', $model->getField('datetime')->toString());

        // datetime without microseconds
        $dt = new \DateTime('2020-01-21 21:09:42');
        $model->set('date', $dt);
        $model->set('time', $dt);
        $model->set('datetime', $dt);

        $this->assertSame($dt->format('Y-m-d'), $model->getField('date')->toString());
        $this->assertSame($dt->format('H:i:s'), $model->getField('time')->toString());
        $this->assertSame($dt->format('c'), $model->getField('datetime')->toString());

        // datetime with microseconds
        $dt = new \DateTime('2020-01-21 21:09:42.895623');
        $model->set('date', $dt);
        $model->set('time', $dt);
        $model->set('datetime', $dt);

        $this->assertSame($dt->format('Y-m-d'), $model->getField('date')->toString());
        $this->assertSame($dt->format('H:i:s.u'), $model->getField('time')->toString());
        $this->assertSame($dt->format('Y-m-d\TH:i:s.uP'), $model->getField('datetime')->toString());
    }

    public function testSetNull()
    {
        $m = new Model();
        $m->addField('a');
        $m->addField('b', ['mandatory' => true]);
        $m->addField('c', ['required' => true]);

        // valid value for set()
        $m->set('a', 'x');
        $m->set('b', 'y');
        $m->set('c', 'z');
        $this->assertSame('x', $m->get('a'));
        $this->assertSame('y', $m->get('b'));
        $this->assertSame('z', $m->get('c'));
        $m->set('a', '');
        $m->set('b', '');
        $this->assertSame('', $m->get('a'));
        $this->assertSame('', $m->get('b'));
        $m->set('a', null);
        $this->assertNull($m->get('a'));

        // null must pass
        $m->setNull('a');
        $m->setNull('b');
        $m->getField('c')->setNull();
        $this->assertNull($m->get('a'));
        $this->assertNull($m->get('b'));
        $this->assertNull($m->get('c'));

        // invalid value for set() - normalization must fail
        $this->expectException(\Phlex\Data\Exception::class);
        $m->set('c', null); // @TODO even "b"/mandatory field should fail!
    }

    public function testBoolean()
    {
        $m = new Model();
        $m->addField('is_vip_2', ['type' => ['boolean', 'valueTrue' => 1, 'valueFalse' => 0]]);
        $m->addField('is_vip_3', ['type' => ['boolean', 'valueTrue' => 'Y', 'valueFalse' => 'N']]);

        $m->set('is_vip_2', 0);
        $this->assertFalse($m->get('is_vip_2'));
        $m->set('is_vip_2', 1);
        $this->assertTrue($m->get('is_vip_2'));
        $m->set('is_vip_2', false);
        $this->assertFalse($m->get('is_vip_2'));
        $m->set('is_vip_2', true);
        $this->assertTrue($m->get('is_vip_2'));

        $m->set('is_vip_3', 'N');
        $this->assertFalse($m->get('is_vip_3'));
        $m->set('is_vip_3', 'Y');
        $this->assertTrue($m->get('is_vip_3'));
        $m->set('is_vip_3', false);
        $this->assertFalse($m->get('is_vip_3'));
        $m->set('is_vip_3', true);
        $this->assertTrue($m->get('is_vip_3'));
    }

    public function testPersistence()
    {
        $m = new Model();
        $m->addField('normal');
        $m->addField('never_save', ['never_save' => true]);
        $m->addField('never_persist', ['never_persist' => true]);

        $this->assertTrue($m->getField('normal')->interactsWithPersistence());
        $this->assertTrue($m->getField('never_save')->interactsWithPersistence());
        $this->assertFalse($m->getField('never_save')->savesToPersistence());
        $this->assertFalse($m->getField('never_persist')->interactsWithPersistence());
        $this->assertFalse($m->getField('never_persist')->savesToPersistence());
        $this->assertFalse($m->getField('never_persist')->loadsFromPersistence());

        $m->getField('normal')->setNeverSave();
        $this->assertFalse($m->getField('normal')->savesToPersistence());
        $this->assertTrue($m->getField('normal')->interactsWithPersistence());

        $m->getField('never_save')->setNeverSave(false);
        $this->assertTrue($m->getField('never_save')->savesToPersistence());
    }

    public function testAccess()
    {
        $m = new Model();
        $m->addField('normal');
        $m->addField('read_only', ['read_only' => true]);

        $this->assertTrue($m->getField('normal')->checkAccess(Model\Field::ACCESS_GET | Model\Field::ACCESS_SET));
        $this->assertFalse($m->getField('read_only')->checkAccess(Model\Field::ACCESS_SET));

        $m->getField('read_only')->grantAccess(Model\Field::ACCESS_SET);
        $this->assertTrue($m->getField('read_only')->checkAccess(Model\Field::ACCESS_SET));

        $m->getField('read_only')->denyAccess(Model\Field::ACCESS_SET);
        $this->assertFalse($m->getField('read_only')->checkAccess(Model\Field::ACCESS_SET));

        $m->getField('read_only')->setReadOnly(false);
        $this->assertTrue($m->getField('read_only')->checkAccess(Model\Field::ACCESS_SET));

        $m->getField('normal')->denyAccess(Model\Field::ACCESS_SET);
        $this->assertFalse($m->getField('normal')->checkAccess(Model\Field::ACCESS_SET));
    }
}
