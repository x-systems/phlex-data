<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Model;

class SD_User extends \Phlex\Data\Tests\Model\User
{
    public $table = 'user';

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->add(new Model\Controller\SoftDelete());
    }
}

class ModelSoftDeleteTest extends Sql\TestCase
{
    public function testBasic()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Peters', 'sdflag_active' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'surname' => 'Sims', 'sdflag_active' => 0],
                3 => ['id' => 3, 'name' => 'Joe', 'surname' => 'Sax', 'sdflag_active' => 1],
            ],
        ]);

        $users = new SD_User($this->db);

        $this->assertSame([1, 3], array_column($users->export(['id']), 'id'));
        $this->assertSame('Soft Delete Status is equal to \'Active\'', $users->scope()->toWords());

        (clone $users)->load(1)->deactivate();

        $this->assertSame([3], array_column($users->export(['id']), 'id'));

        $users->setOption(Model\Controller\SoftDelete::OPTION_RETRIEVE, Model\Controller\SoftDelete::RETRIEVE_INACTIVE);

        $this->assertSame([1, 2], array_column($users->export(['id']), 'id'));
        $this->assertSame('Soft Delete Status is equal to \'Deactivated\'', $users->scope()->toWords());

        $users->setOption(Model\Controller\SoftDelete::OPTION_RETRIEVE, Model\Controller\SoftDelete::RETRIEVE_ALL);

        $this->assertSame([1, 2, 3], array_column($users->export(['id']), 'id'));
        $this->assertSame('Soft Delete Status is equal to \'Any value\'', $users->scope()->toWords());

        $users->save([
            'name' => 'Jane',
            'surname' => 'Stevens',
        ]);

        $users->setOption(Model\Controller\SoftDelete::OPTION_RETRIEVE, Model\Controller\SoftDelete::RETRIEVE_ACTIVE);

        $this->assertSame([3, 4], array_column($users->export(['id']), 'id'));
        $this->assertSame('Soft Delete Status is equal to \'Active\'', $users->scope()->toWords());
    }
}