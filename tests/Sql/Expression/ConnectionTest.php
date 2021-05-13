<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Sql\Expression;

use Phlex\Core\Exception;
use Phlex\Core\PHPUnit;
use Phlex\Data\Persistence;

class DummyConnection extends Persistence\Sql
{
    public function groupConcat($field, string $delimiter = ','): Persistence\Sql\Expression
    {
        return new Persistence\Sql\Expression('dummy');
    }
}

class DummyConnection2 extends Persistence\Sql
{
    public function groupConcat($field, string $delimiter = ','): Persistence\Sql\Expression
    {
        return new Persistence\Sql\Expression('dummy');
    }
}

class DummyConnection3 extends Persistence\Sql
{
    public function groupConcat($field, string $delimiter = ','): Persistence\Sql\Expression
    {
        return new Persistence\Sql\Expression('dummy');
    }
}

class DummyConnection4 extends Persistence\Sql
{
    public function groupConcat($field, string $delimiter = ','): Persistence\Sql\Expression
    {
        return new Persistence\Sql\Expression('dummy');
    }
}

class ConnectionTest extends PHPUnit\TestCase
{
    /**
     * Test constructor.
     */
    public function testInit()
    {
        $c = Persistence\Sql::connect('sqlite::memory:');
        $this->assertSame(
            '4',
            $c->expr('select (2+2)')->execute()->fetchOne()
        );
    }

    /**
     * Test DSN normalize.
     */
    public function testDsnNormalize()
    {
        // standard
        $dsn = Persistence\Sql::normalizeDsn('mysql://root:pass@localhost/db');
        $this->assertSame(['dsn' => 'mysql:host=localhost;dbname=db', 'user' => 'root', 'pass' => 'pass', 'driverSchema' => 'mysql', 'rest' => 'host=localhost;dbname=db'], $dsn);

        $dsn = Persistence\Sql::normalizeDsn('mysql:host=localhost;dbname=db');
        $this->assertSame(['dsn' => 'mysql:host=localhost;dbname=db', 'user' => null, 'pass' => null, 'driverSchema' => 'mysql', 'rest' => 'host=localhost;dbname=db'], $dsn);

        $dsn = Persistence\Sql::normalizeDsn('mysql:host=localhost;dbname=db', 'root', 'pass');
        $this->assertSame(['dsn' => 'mysql:host=localhost;dbname=db', 'user' => 'root', 'pass' => 'pass', 'driverSchema' => 'mysql', 'rest' => 'host=localhost;dbname=db'], $dsn);

        // username and password should take precedence
        $dsn = Persistence\Sql::normalizeDsn('mysql://root:pass@localhost/db', 'foo', 'bar');
        $this->assertSame(['dsn' => 'mysql:host=localhost;dbname=db', 'user' => 'foo', 'pass' => 'bar', 'driverSchema' => 'mysql', 'rest' => 'host=localhost;dbname=db'], $dsn);

        // more options
        $dsn = Persistence\Sql::normalizeDsn('mysql://root:pass@localhost/db;foo=bar');
        $this->assertSame(['dsn' => 'mysql:host=localhost;dbname=db;foo=bar', 'user' => 'root', 'pass' => 'pass', 'driverSchema' => 'mysql', 'rest' => 'host=localhost;dbname=db;foo=bar'], $dsn);

        // no password
        $dsn = Persistence\Sql::normalizeDsn('mysql://root@localhost/db');
        $this->assertSame(['dsn' => 'mysql:host=localhost;dbname=db', 'user' => 'root', 'pass' => null, 'driverSchema' => 'mysql', 'rest' => 'host=localhost;dbname=db'], $dsn);
        $dsn = Persistence\Sql::normalizeDsn('mysql://root:@localhost/db'); // see : after root
        $this->assertSame(['dsn' => 'mysql:host=localhost;dbname=db', 'user' => 'root', 'pass' => null, 'driverSchema' => 'mysql', 'rest' => 'host=localhost;dbname=db'], $dsn);

        $dsn = Persistence\Sql::normalizeDsn('sqlite::memory');
        $this->assertSame(['dsn' => 'sqlite::memory', 'user' => null, 'pass' => null, 'driverSchema' => 'sqlite', 'rest' => ':memory'], $dsn); // rest is unusable anyway in this context

        // with port number as URL, normalize port to ;port=1234
        $dsn = Persistence\Sql::normalizeDsn('mysql://root:pass@localhost:1234/db');
        $this->assertSame(['dsn' => 'mysql:host=localhost;port=1234;dbname=db', 'user' => 'root', 'pass' => 'pass', 'driverSchema' => 'mysql', 'rest' => 'host=localhost;port=1234;dbname=db'], $dsn);

        // with port number as DSN, leave port as :port
        $dsn = Persistence\Sql::normalizeDsn('mysql:host=localhost:1234;dbname=db');
        $this->assertSame(['dsn' => 'mysql:host=localhost:1234;dbname=db', 'user' => null, 'pass' => null, 'driverSchema' => 'mysql', 'rest' => 'host=localhost:1234;dbname=db'], $dsn);
    }

    public function testPersistenceRegistry()
    {
        DummyConnection::registerPersistenceSeed('dummy');
        $this->assertSame([DummyConnection::class], Persistence\Sql::resolvePersistenceSeed('dummy'));

        Persistence\Sql::registerPersistenceSeed('dummy2', [DummyConnection2::class]);
        Persistence\Sql::registerPersistenceSeed('dummy3', [DummyConnection3::class]);

        $this->assertSame([DummyConnection2::class], Persistence\Sql::resolvePersistenceSeed('dummy2'));

        $this->assertSame([DummyConnection3::class], Persistence\Sql::resolvePersistenceSeed('dummy3'));

        DummyConnection4::registerPersistenceSeed('dummy4');
        $this->assertSame([DummyConnection4::class], Persistence\Sql::resolvePersistenceSeed('dummy4'));
    }

    public function testMysqlFail()
    {
        $this->expectException(\Exception::class);
        Persistence\Sql::connect('mysql:host=256.256.256.256'); // invalid host
    }

    public function testException1()
    {
        $this->expectException(Exception::class);
        Persistence\Sql::connect(':');
    }

    public function testException2()
    {
        $this->expectException(Exception::class);
        Persistence\Sql::connect('');
    }

    public function testException3()
    {
        $this->expectException(\PDOException::class);
        new Persistence\Sql\Platform\Sqlite('sqlite::memory');
    }

//     public function testException4()
//     {
//         $c = new Persistence\Sql\Platform\Sqlite();
//         $q = $c->expr('select (2+2)');

//         $this->assertSame(
//             'select (2+2)',
//             $q->render()
//         );

//         $this->expectException(Exception::class);
//         $q->execute();
//     }
}
