<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Model;

/**
 * @method deactivate()
 * @method reactivate()
 * @method isActive()
 * @method ignoringSoftDeleteFlag(\Closure $fx)
 */
class SD_User extends \Phlex\Data\Tests\Model\User
{
    public $table = 'user';

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->add(new Model\SoftDeleteController());
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

        $john = $users->load(1)->deactivate();
        $this->assertFalse($john->isActive());
        $this->assertTrue($users->load(3)->isActive());
        $this->assertSame([3], self::getEntiityIds($users));

        $users->setOption(Model\SoftDeleteController::OPTION_RETRIEVE, Model\SoftDeleteController::RETRIEVE_INACTIVE);

        $this->assertSame([1, 2], self::getEntiityIds($users));
        $this->assertSame('Soft Delete Status is equal to \'Deactivated\'', $users->scope()->toWords());

        $users->setOption(Model\SoftDeleteController::OPTION_RETRIEVE, Model\SoftDeleteController::RETRIEVE_ALL);

        $this->assertSame([1, 2, 3], self::getEntiityIds($users));
        $this->assertSame('Soft Delete Status is equal to \'Any value\'', $users->scope()->toWords());

        $jane = $users->save([
            'name' => 'Jane',
            'surname' => 'Stevens',
        ]);

        $this->assertTrue($jane->isActive());

        $users->setOption(Model\SoftDeleteController::OPTION_RETRIEVE, Model\SoftDeleteController::RETRIEVE_ACTIVE);

        $this->assertSame([3, 4], self::getEntiityIds($users));
        $this->assertSame('Soft Delete Status is equal to \'Active\'', $users->scope()->toWords());
    }

    protected static function getEntiityIds($model): array
    {
        $ids = array_column($model->export(['id']), 'id');
        sort($ids);

        return $ids;
    }

    public function testInactiveRecordNotFound()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Peters', 'sdflag_active' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'surname' => 'Sims', 'sdflag_active' => 0],
                3 => ['id' => 3, 'name' => 'Joe', 'surname' => 'Sax', 'sdflag_active' => 1],
            ],
        ]);

        $users = new SD_User($this->db);

        $this->expectException(Model\RecordNotFoundException::class);
        $users->load(2);
    }

    public function testAdminMode()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Peters', 'sdflag_active' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'surname' => 'Sims', 'sdflag_active' => 0],
                3 => ['id' => 3, 'name' => 'Joe', 'surname' => 'Sax', 'sdflag_active' => 1],
            ],
        ]);

        $users = new SD_User($this->db);

        $users->ignoringSoftDeleteFlag(function ($users) {
            $users->load(2)->reactivate();
        });

        $this->assertTrue($users->load(2)->isActive());
    }
}
