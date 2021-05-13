<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Sql\Expression\WithDb;

use Doctrine\DBAL\Platforms\OraclePlatform;
use Phlex\Core\PHPUnit;
use Phlex\Data\Persistence;

class ConnectionTest extends PHPUnit\TestCase
{
    /**
     * @doesNotPerformAssertions
     */
    public function testServerConnection()
    {
        $c = Persistence\Sql::connect($GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']);

        return (string) $c->expr('SELECT 1' . ($c->connection->getDatabasePlatform() instanceof OraclePlatform ? ' FROM DUAL' : ''))->execute()->fetchOne();
    }
}
