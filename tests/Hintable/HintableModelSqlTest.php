<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Hintable;

use Phlex\Data\Persistence;

class HintableModelSqlTest extends HintableModelArrayTest
{
    protected function createPersistence(): Persistence
    {
        $db = Persistence\Sql::connect('sqlite::memory:');

        (new Model\Simple($db))->migrate();
        (new Model\Standard($db))->migrate();

        return $db;
    }
}
