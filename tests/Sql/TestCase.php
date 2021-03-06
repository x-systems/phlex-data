<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Sql;

use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

// NOTE: This class should stay here in this namespace because other repos rely on it. For example, Phlex\Data tests
class TestCase extends \Phlex\Core\PHPUnit\TestCase
{
    /** @var Persistence\Sql Persistence instance */
    public $db;

    /** @var array Array of database table names */
    public $tables;

    /** @var bool Debug mode enabled/disabled. In debug mode SQL queries are dumped. */
    public $debug = false;

    /**
     * Setup test database.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // establish connection
        $dsn = $GLOBALS['DB_DSN'] ?? 'sqlite::memory:';
        $user = $GLOBALS['DB_USER'] ?? null;
        $pass = $GLOBALS['DB_PASSWD'] ?? null;

        $this->db = Persistence\Sql::connect($dsn, $user, $pass);

        // reset DB autoincrement to 1, tests rely on it
        if ($this->getDatabasePlatform() instanceof MySQLPlatform) {
            $this->db->execute('SET @@auto_increment_offset=1, @@auto_increment_increment=1');
        }

        if ($this->debug) {
            $this->db->getConnection()->getConfiguration()->setSQLLogger(
                new class($this) implements SQLLogger {
                    /** @var TestCase */
                    public $testCase;

                    public function __construct(TestCase $testCase)
                    {
                        $this->testCase = $testCase;
                    }

                    public function startQuery($sql, $params = null, $types = null): void
                    {
                        if (!$this->testCase->debug) {
                            return;
                        }

                        echo "\n" . $sql . "\n" . print_r($params, true) . "\n\n";
                    }

                    public function stopQuery(): void
                    {
                    }
                }
            );
        }
    }

    protected function tearDown(): void
    {
        $this->db = null; // @phpstan-ignore-line
    }

    protected function getDatabasePlatform(): AbstractPlatform
    {
        return $this->db->getConnection()->getDatabasePlatform();
    }

    protected function createSchemaManager(): AbstractSchemaManager
    {
        return $this->db->getConnection()->createSchemaManager();
    }

    private function convertSqlFromSqlite(string $sql): string
    {
        return preg_replace_callback(
            '~\'(?:[^\'\\\\]+|\\\\.)*\'|"(?:[^"\\\\]+|\\\\.)*"~s',
            function ($matches) {
                $str = substr(preg_replace('~\\\\(.)~s', '$1', $matches[0]), 1, -1);
                if (substr($matches[0], 0, 1) === '"') {
                    return $this->getDatabasePlatform()->quoteSingleIdentifier($str);
                }

                return $this->getDatabasePlatform()->quoteStringLiteral($str);
            },
            $sql
        );
    }

    protected function assertSameSql(string $expectedSqliteSql, string $actualSql, string $message = ''): void
    {
        $this->assertSame($this->convertSqlFromSqlite($expectedSqliteSql), $actualSql, $message);
    }

    public function createMigrator(Model $model = null): Persistence\Sql\Migration
    {
        return new Persistence\Sql\Migration($model ?: $this->db);
    }

    /**
     * Use this method to clean up tables after you have created them,
     * so that your database would be ready for the next test.
     */
    public function dropTableIfExists(string $tableName)
    {
        // we can not use SchemaManager::dropTable directly because of
        // our custom Oracle sequence for PK/AI
        $this->createMigrator()->table($tableName)->dropIfExists();
    }

    /**
     * Sets database into a specific test.
     */
    public function setDb(array $dbData, bool $importData = true)
    {
        $this->tables = array_keys($dbData);

        // create tables
        foreach ($dbData as $tableName => $data) {
            $this->dropTableIfExists($tableName);

            $first_row = current($data);
            if ($first_row) {
                $model = new Model($this->db, ['table' => $tableName]);

                foreach ($first_row as $key => $row) {
                    if ($key === 'id') {
                        continue;
                    }

                    if (is_int($row)) {
                        $fieldType = 'integer';
                    } elseif (is_float($row)) {
                        $fieldType = 'float';
                    } elseif ($row instanceof \DateTimeInterface) {
                        $fieldType = 'datetime';
                    } else {
                        $fieldType = 'string';
                    }

                    $model->addField($key, ['type' => $fieldType]);
                }

                $model->migrate();
            }

            // import data
            if ($importData) {
                $hasId = (bool) key($data);

                foreach ($data as $id => $row) {
                    if ($id === '_') {
                        continue;
                    }

                    $query = $this->db->statement()
                        ->insert()
                        ->table($tableName)
                        ->set($row);

                    if (!isset($row['id']) && $hasId) {
                        $query->set('id', $id);
                    }

                    $query->execute();
                }
            }
        }
    }

    /**
     * Return database data.
     */
    public function getDb(array $tableNames = null, bool $noId = false): array
    {
        if ($tableNames === null) {
            $tableNames = $this->tables;
        }

        $ret = [];

        foreach ($tableNames as $table) {
            $data2 = [];

            $data = $this->db->statement()->table($table)->execute()->fetchAllAssociative();

            foreach ($data as &$row) {
                foreach ($row as &$v) {
                    if (is_int($v) || is_float($v)) {
                        $v = (string) $v;
                    } elseif (is_bool($v)) {
                        $v = $v ? '1' : '0';
                    }

                    if (is_resource($v) && get_resource_type($v) === 'stream'
                        && $this->db->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\OraclePlatform) {
                        $v = stream_get_contents($v);
                    }
                }

                if ($noId) {
                    unset($row['id']);
                    $data2[] = $row;
                } else {
                    $data2[$row['id']] = $row;
                }
            }

            $ret[$table] = $data2;
        }

        return $ret;
    }

    /**
     * @param mixed $a
     * @param mixed $b
     */
    private function compareExportUnorderedValue($a, $b): int
    {
        if ($a === $b) {
            return 0;
        }

        $cmp = gettype($a) <=> gettype($b);
        if ($cmp !== 0) {
            return $cmp;
        }

        if (is_object($a)) {
            $cmp = gettype($a) <=> gettype($b);
            if ($cmp !== 0) {
                return $cmp;
            }

            if ($a instanceof \DateTimeInterface) {
                $format = 'Y-m-d H:i:s.u e I Z';

                return $a->format($format) <=> $b->format($format);
            }
        }

        if (is_array($a) && count($a) === count($b)) {
            $is2d = true;
            foreach ($a as $v) {
                if (!is_array($v)) {
                    $is2d = false;

                    break;
                }
            }
            if ($is2d) {
                foreach ($b as $v) {
                    if (!is_array($v)) {
                        $is2d = false;

                        break;
                    }
                }
            }

            if ($is2d) {
                if (array_is_list($a) && array_is_list($b)) {
                    usort($a, fn ($a, $b) => $this->compareExportUnorderedValue($a, $b));
                    usort($b, fn ($a, $b) => $this->compareExportUnorderedValue($a, $b));
                } else {
                    uasort($a, fn ($a, $b) => $this->compareExportUnorderedValue($a, $b));
                    uasort($b, fn ($a, $b) => $this->compareExportUnorderedValue($a, $b));
                }
            }

            if (array_keys($a) === array_keys($b)) {
                foreach ($a as $k => $v) {
                    $cmp = $this->compareExportUnorderedValue($v, $b[$k]);
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                }

                return 0;
            }
        }

        return $a <=> $b;
    }

    /**
     * Same as self::assertSame() except:
     * - 2D arrays (rows) are recursively compared without any order
     * - objects implementing DateTimeInterface are compared by formatted output.
     */
    protected function assertSameExportUnordered(array $expected, array $actual, string $message = ''): void
    {
        if ($this->compareExportUnorderedValue($expected, $actual) === 0) {
            $this->assertTrue(true);

            return;
        }

        $this->assertSame($expected, $actual, $message);
    }
}
