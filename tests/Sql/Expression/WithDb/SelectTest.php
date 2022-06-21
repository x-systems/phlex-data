<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Sql\Expression\WithDb;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Phlex\Core\PHPUnit;
use Phlex\Data\Persistence;

class SelectTest extends PHPUnit\TestCase
{
    protected $persistence;

    private function dropDbIfExists(): void
    {
        if ($this->persistence->connection->getDatabasePlatform() instanceof OraclePlatform) {
            $this->persistence->connection->executeQuery('begin
                execute immediate \'drop table "employee"\';
            exception
                when others then
                    if sqlcode != -942 then
                        raise;
                    end if;
            end;');
        } else {
            $this->persistence->connection->executeQuery('DROP TABLE IF EXISTS employee');
        }
    }

    protected function setUp(): void
    {
        $this->persistence = Persistence\Sql::connect($GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']);

        $this->dropDbIfExists();

        $strType = $this->persistence->connection->getDatabasePlatform() instanceof OraclePlatform ? 'varchar2' : 'varchar';
        $boolType = ['mssql' => 'bit', 'oracle' => 'number(1)'][$this->persistence->connection->getDatabasePlatform()->getName()] ?? 'bool';
        $fixIdentifiersFunc = function ($sql) {
            return preg_replace_callback('~(?:\'(?:\'\'|\\\\\'|[^\'])*\')?+\K"([^\'"()\[\]{}]*?)"~s', function ($matches) {
                if ($this->persistence->connection->getDatabasePlatform() instanceof MySQLPlatform) {
                    return '`' . $matches[1] . '`';
                } elseif ($this->persistence->connection->getDatabasePlatform() instanceof SQLServer2012Platform) {
                    return '[' . $matches[1] . ']';
                }

                return '"' . $matches[1] . '"';
            }, $sql);
        };
        $this->persistence->connection->executeQuery($fixIdentifiersFunc('CREATE TABLE "employee" ("id" int not null, "name" ' . $strType . '(100), "surname" ' . $strType . '(100), "retired" ' . $boolType . ', ' . ($this->persistence->connection->getDatabasePlatform() instanceof OraclePlatform ? 'CONSTRAINT "employee_pk" ' : '') . 'PRIMARY KEY ("id"))'));
        foreach ([
            ['id' => 1, 'name' => 'Oliver', 'surname' => 'Smith', 'retired' => false],
            ['id' => 2, 'name' => 'Jack', 'surname' => 'Williams', 'retired' => true],
            ['id' => 3, 'name' => 'Harry', 'surname' => 'Taylor', 'retired' => true],
            ['id' => 4, 'name' => 'Charlie', 'surname' => 'Lee', 'retired' => false],
        ] as $row) {
            $this->persistence->connection->executeQuery($fixIdentifiersFunc('INSERT INTO "employee" (' . implode(', ', array_map(fn ($v) => '"' . $v . '"', array_keys($row))) . ') VALUES(' . implode(', ', array_map(function ($v) {
                if (is_bool($v)) {
                    if ($this->persistence->connection->getDatabasePlatform() instanceof PostgreSQL94Platform) {
                        return $v ? 'true' : 'false';
                    }

                    return $v ? 1 : 0;
                } elseif (is_int($v) || is_float($v)) { // @phpstan-ignore-line
                    return $v;
                }

                return '\'' . $v . '\'';
            }, $row)) . ')'));
        }
    }

    protected function tearDown(): void
    {
        $this->dropDbIfExists();

        $this->persistence = null;
    }

    private function q($table = null, $alias = null)
    {
        $q = $this->persistence->statement();

        // add table to query if specified
        if ($table !== null) {
            $q->table($table, $alias);
        }

        return $q;
    }

    public function testBasicQueries()
    {
        $this->assertSame(4, count($this->q('employee')->execute()->fetchAllAssociative()));

        $this->assertSame(
            ['name' => 'Oliver', 'surname' => 'Smith'],
            $this->q('employee')->field('name,surname')->execute()->fetchAssociative()
        );

        $this->assertSame(
            ['surname' => 'Williams'],
            $this->q('employee')->field('surname')->where('retired', '1')->execute()->fetchAssociative()
        );

        $this->assertSame(
            '4',
            (string) $this->q()->field(new Persistence\Sql\Expression('2+2'))->execute()->fetchOne()
        );

        $this->assertSame(
            '4',
            (string) $this->q('employee')->field(new Persistence\Sql\Expression('count(*)'))->execute()->fetchOne()
        );

        $names = [];
        foreach ($this->q('employee')->where('retired', false) as $row) {
            $names[] = $row['name'];
        }

        $this->assertSame(
            ['Oliver', 'Charlie'],
            $names
        );

        $this->assertEquals(
            [['now' => '4']],
            $this->q()->field(new Persistence\Sql\Expression('2+2'), 'now')->execute()->fetchAllAssociative()
        );

        /*
         * PostgreSQL needs to have values cast, to make the query work.
         * But CAST(.. AS int) does not work in mysql. So we use two different tests..
         * (CAST(.. AS int) will work on mariaDB, whereas mysql needs it to be CAST(.. AS signed))
         */
        if ($this->persistence->connection->getDatabasePlatform() instanceof PostgreSQL94Platform) {
            $this->assertEquals(
                [['now' => '6']],
                $this->q()->field(new Persistence\Sql\Expression('CAST([] AS int)+CAST([] AS int)', [3, 3]), 'now')->execute()->fetchAllAssociative()
            );
        } else {
            $this->assertEquals(
                [['now' => '6']],
                $this->q()->field(new Persistence\Sql\Expression('[]+[]', [3, 3]), 'now')->execute()->fetchAllAssociative()
            );
        }

        $this->assertSame(
            '5',
            (string) $this->q()->field(new Persistence\Sql\Expression('COALESCE([], \'5\')', [null]), 'null_test')->execute()->fetchOne()
        );
    }

    public function testExpression()
    {
        /*
         * PostgreSQL, at least versions before 10, needs to have the string cast to the
         * correct datatype.
         * But using CAST(.. AS CHAR) will return one single character on postgresql, but the
         * entire string on mysql.
         */
        if ($this->persistence->connection->getDatabasePlatform() instanceof PostgreSQL94Platform || $this->persistence->connection->getDatabasePlatform() instanceof SQLServer2012Platform) {
            $this->assertSame(
                'foo',
                $this->persistence->execute(new Persistence\Sql\Expression('select CAST([] AS VARCHAR)', ['foo']))->fetchOne()
            );
        } elseif ($this->persistence->connection->getDatabasePlatform() instanceof OraclePlatform) {
            $this->assertSame(
                'foo',
                $this->persistence->execute(new Persistence\Sql\Expression('select CAST([] AS VARCHAR2(100)) FROM DUAL', ['foo']))->fetchOne()
            );
        } else {
            $this->assertSame(
                'foo',
                $this->persistence->execute(new Persistence\Sql\Expression('select CAST([] AS CHAR)', ['foo']))->fetchOne()
            );
        }
    }

    public function testOtherQueries()
    {
        // truncate table
        $this->q('employee')->truncate()->execute();
        $this->assertSame(
            '0',
            (string) $this->q('employee')->field(new Persistence\Sql\Expression('count(*)'))->execute()->fetchOne()
        );

        // insert
        $this->q('employee')
            ->set(['id' => 1, 'name' => 'John', 'surname' => 'Doe', 'retired' => 1])
            ->insert()->execute();
        $this->q('employee')
            ->set(['id' => 2, 'name' => 'Jane', 'surname' => 'Doe', 'retired' => 0])
            ->insert()->execute();
        $this->assertEquals(
            [['id' => '1', 'name' => 'John'], ['id' => '2', 'name' => 'Jane']],
            $this->q('employee')->field('id,name')->order('id')->execute()->fetchAllAssociative()
        );

        // update
        $this->q('employee')
            ->where('name', 'John')
            ->set('name', 'Johnny')
            ->update()->execute();
        $this->assertEquals(
            [['id' => '1', 'name' => 'Johnny'], ['id' => '2', 'name' => 'Jane']],
            $this->q('employee')->field('id,name')->order('id')->execute()->fetchAllAssociative()
        );

        // replace
        if ($this->persistence->connection->getDatabasePlatform() instanceof PostgreSQL94Platform || $this->persistence->connection->getDatabasePlatform() instanceof SQLServer2012Platform || $this->persistence->connection->getDatabasePlatform() instanceof OraclePlatform) {
            $this->q('employee')
                ->set(['name' => 'Peter', 'surname' => 'Doe', 'retired' => 1])
                ->where('id', 1)
                ->update()->execute();
        } else {
            $this->q('employee')
                ->set(['id' => 1, 'name' => 'Peter', 'surname' => 'Doe', 'retired' => 1])
                ->replace()->execute();
        }

        // In SQLite replace is just like insert, it just checks if there is
        // duplicate key and if it is it deletes the row, and inserts the new
        // one, otherwise it just inserts.
        // So order of records after REPLACE in SQLite will be [Jane, Peter]
        // not [Peter, Jane] as in MySQL, which in theory does the same thing,
        // but returns [Peter, Jane] - in original order.
        // That's why we add usort here.
        $data = $this->q('employee')->field('id,name')->execute()->fetchAllAssociative();
        usort($data, fn ($a, $b) => $a['id'] - $b['id']);
        $this->assertEquals(
            [['id' => '1', 'name' => 'Peter'], ['id' => '2', 'name' => 'Jane']],
            $data
        );

        // delete
        $this->q('employee')
            ->where('retired', 1)
            ->delete()->execute();
        $this->assertEquals(
            [['id' => '2', 'name' => 'Jane']],
            $this->q('employee')->field('id,name')->execute()->fetchAllAssociative()
        );
    }

    public function testEmptyGetOne()
    {
        // truncate table
        $this->q('employee')->truncate()->execute();
        $this->assertFalse($this->q('employee')->field('name')->execute()->fetchOne());
    }

    public function testWhereExpression()
    {
        $this->assertEquals(
            [['id' => '2', 'name' => 'Jack', 'surname' => 'Williams', 'retired' => '1']],
            $this->q('employee')->where('retired', 1)->where(new Persistence\Sql\Expression('{}=[] or {}=[]', ['surname', 'Williams', 'surname', 'Smith']))->execute()->fetchAllAssociative()
        );
    }

    public function testExecuteException()
    {
        $this->expectException(Persistence\Sql\ExecuteException::class);

        try {
            $this->q('non_existing_table')->field('non_existing_field')->execute()->fetchOne();
        } catch (Persistence\Sql\ExecuteException $e) {
            // test error code
            $unknownFieldErrorCode = [
                'sqlite' => 1,        // SQLSTATE[HY000]: General error: 1 no such table: non_existing_table
                'mysql' => 1146,      // SQLSTATE[42S02]: Base table or view not found: 1146 Table 'non_existing_table' doesn't exist
                'postgresql' => 7,    // SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "non_existing_table" does not exist
                'mssql' => 208,       // SQLSTATE[42S02]: Invalid object name 'non_existing_table'
                'oracle' => 942,      // SQLSTATE[HY000]: ORA-00942: table or view does not exist
            ][$this->persistence->connection->getDatabasePlatform()->getName()];
            $this->assertSame($unknownFieldErrorCode, $e->getCode());

            // test debug query
            $expectedQuery = [
                'mysql' => 'select `non_existing_field` from `non_existing_table`',
                'mssql' => 'select [non_existing_field] from [non_existing_table]',
            ][$this->persistence->connection->getDatabasePlatform()->getName()] ?? 'select "non_existing_field" from "non_existing_table"';
            $this->assertSame(preg_replace('~\s+~', '', $expectedQuery), preg_replace('~\s+~', '', $e->getDebugQuery()));

            throw $e;
        }
    }
}
