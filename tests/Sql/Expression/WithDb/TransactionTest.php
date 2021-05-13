<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Sql\Expression\WithDb;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Phlex\Core\PHPUnit;
use Phlex\Data\Exception;
use Phlex\Data\Persistence;

class TransactionTest extends PHPUnit\TestCase
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
            $this->persistence->connection->executeQuery($fixIdentifiersFunc('INSERT INTO "employee" (' . implode(', ', array_map(function ($v) {
                return '"' . $v . '"';
            }, array_keys($row))) . ') VALUES(' . implode(', ', array_map(function ($v) {
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

    private function e($template = null, $args = null)
    {
        return $this->persistence->expr($template, $args);
    }

    public function testCommitException1()
    {
        // try to commit when not in transaction
        $this->expectException(Exception::class);
        $this->persistence->commit();
    }

    public function testCommitException2()
    {
        // try to commit when not in transaction anymore
        $this->persistence->beginTransaction();
        $this->persistence->commit();
        $this->expectException(Exception::class);
        $this->persistence->commit();
    }

    public function testRollbackException1()
    {
        // try to rollback when not in transaction
        $this->expectException(Exception::class);
        $this->persistence->rollBack();
    }

    public function testRollbackException2()
    {
        // try to rollback when not in transaction anymore
        $this->persistence->beginTransaction();
        $this->persistence->rollBack();
        $this->expectException(Exception::class);
        $this->persistence->rollBack();
    }

    /**
     * Tests simple and nested transactions.
     */
    public function testTransactions()
    {
        // truncate table, prepare
        $this->q('employee')->truncate()->execute();
        $this->assertSame(
            '0',
            $this->q('employee')->field(new Persistence\Sql\Expression('count(*)'))->execute()->fetchOne()
        );

        // without transaction, ignoring exceptions
        try {
            $this->q('employee')
                ->set(['id' => 1, 'name' => 'John', 'surname' => 'Doe', 'retired' => 1])
                ->insert()->execute();
            $this->q('employee')
                ->set(['id' => 2, 'FOO' => 'bar', 'name' => 'Jane', 'surname' => 'Doe', 'retired' => 0])
                ->insert()->execute();
        } catch (\Exception $e) {
            // ignore
        }

        $this->assertSame(
            '1',
            $this->q('employee')->field(new Persistence\Sql\Expression('count(*)'))->execute()->fetchOne()
        );

        // 1-level transaction: begin, insert, 2, rollback, 1
        $this->persistence->beginTransaction();
        $this->q('employee')
            ->set(['id' => 3, 'name' => 'John', 'surname' => 'Doe', 'retired' => 1])
            ->insert()->execute();
        $this->assertSame(
            '2',
            $this->q('employee')->field(new Persistence\Sql\Expression('count(*)'))->execute()->fetchOne()
        );

        $this->persistence->rollBack();
        $this->assertSame(
            '1',
            $this->q('employee')->field(new Persistence\Sql\Expression('count(*)'))->execute()->fetchOne()
        );

        // atomic method, rolls back everything inside atomic() callback in case of exception
        try {
            $this->persistence->atomic(function () {
                $this->q('employee')
                    ->set(['id' => 3, 'name' => 'John', 'surname' => 'Doe', 'retired' => 1])
                    ->insert()->execute();
                $this->q('employee')
                    ->set(['id' => 4, 'FOO' => 'bar', 'name' => 'Jane', 'surname' => 'Doe', 'retired' => 0])
                    ->insert()->execute();
            });
        } catch (\Exception $e) {
            // ignore
        }

        $this->assertSame(
            '1',
            $this->q('employee')->field(new Persistence\Sql\Expression('count(*)'))->execute()->fetchOne()
        );

        // atomic method, nested atomic transaction, rolls back everything
        try {
            $this->persistence->atomic(function () {
                $this->q('employee')
                    ->set(['id' => 3, 'name' => 'John', 'surname' => 'Doe', 'retired' => 1])
                    ->insert()->execute();

                // success, in, fail, out, fail
                $this->persistence->atomic(function () {
                    $this->q('employee')
                        ->set(['id' => 4, 'name' => 'John', 'surname' => 'Doe', 'retired' => 1])
                        ->insert()->execute();
                    $this->q('employee')
                        ->set(['id' => 5, 'FOO' => 'bar', 'name' => 'Jane', 'surname' => 'Doe', 'retired' => 0])
                        ->insert()->execute();
                });

                $this->q('employee')
                    ->set(['id' => 6, 'FOO' => 'bar', 'name' => 'Jane', 'surname' => 'Doe', 'retired' => 0])
                    ->insert()->execute();
            });
        } catch (\Exception $e) {
            // ignore
        }

        $this->assertSame(
            '1',
            $this->q('employee')->field(new Persistence\Sql\Expression('count(*)'))->execute()->fetchOne()
        );

        // atomic method, nested atomic transaction, rolls back everything
        try {
            $this->persistence->atomic(function () {
                $this->q('employee')
                    ->set(['id' => 3, 'name' => 'John', 'surname' => 'Doe', 'retired' => 1])
                    ->insert()->execute();

                // success, in, success, out, fail
                $this->persistence->atomic(function () {
                    $this->q('employee')
                        ->set(['id' => 4, 'name' => 'John', 'surname' => 'Doe', 'retired' => 1])
                        ->insert()->execute();
                });

                $this->q('employee')
                    ->set(['id' => 5, 'FOO' => 'bar', 'name' => 'Jane', 'surname' => 'Doe', 'retired' => 0])
                    ->insert()->execute();
            });
        } catch (\Exception $e) {
            // ignore
        }

        $this->assertSame(
            '1',
            $this->q('employee')->field(new Persistence\Sql\Expression('count(*)'))->execute()->fetchOne()
        );

        // atomic method, nested atomic transaction, rolls back everything
        try {
            $this->persistence->atomic(function () {
                $this->q('employee')
                    ->set(['id' => 3, 'name' => 'John', 'surname' => 'Doe', 'retired' => 1])
                    ->insert()->execute();

                // success, in, fail, out, catch exception
                $this->persistence->atomic(function () {
                    $this->q('employee')
                        ->set(['id' => 4, 'FOO' => 'bar', 'name' => 'John', 'surname' => 'Doe', 'retired' => 1])
                        ->insert()->execute();
                });

                $this->q('employee')
                    ->set(['id' => 5, 'name' => 'Jane', 'surname' => 'Doe', 'retired' => 0])
                    ->insert()->execute();
            });
        } catch (\Exception $e) {
            // ignore
        }

        $this->assertSame(
            '1',
            $this->q('employee')->field(new Persistence\Sql\Expression('count(*)'))->execute()->fetchOne()
        );

        // atomic method, success - commit
        try {
            $this->persistence->atomic(function () {
                $this->q('employee')
                    ->set(['id' => 3, 'name' => 'John', 'surname' => 'Doe', 'retired' => 1])
                    ->insert()->execute();
            });
        } catch (\Exception $e) {
            // ignore
        }

        $this->assertSame(
            '2',
            $this->q('employee')->field(new Persistence\Sql\Expression('count(*)'))->execute()->fetchOne()
        );
    }

    /**
     * Tests inTransaction().
     */
    public function testInTransaction()
    {
        // inTransaction tests
        $this->assertFalse(
            $this->persistence->inTransaction()
        );

        $this->persistence->beginTransaction();
        $this->assertTrue(
            $this->persistence->inTransaction()
        );

        $this->persistence->rollBack();
        $this->assertFalse(
            $this->persistence->inTransaction()
        );

        $this->persistence->beginTransaction();
        $this->persistence->commit();
        $this->assertFalse(
            $this->persistence->inTransaction()
        );
    }
}
