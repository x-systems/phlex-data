<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Persistence\Sql\Expression\JsonContains;

class JsonContainsTest extends Sql\TestCase
{
    public function testBasic()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Peters', 'json' => '["test_value", "other_value"]'],
                2 => ['id' => 2, 'name' => 'Peter', 'surname' => 'Sims', 'json' => '["zzz_value", "yyy_value"]'],
                3 => ['id' => 3, 'name' => 'Joe', 'surname' => 'Sax', 'json' => '["aaa_value", "bbb_value"]'],
            ],
        ]);

        $users = new Model\User($this->db, ['table' => 'user']);

        $users->addCondition(new JsonContains('json', 'test_value'));

        $this->assertSame([1], array_column($users->export(['id']), 'id'));
    }
}
