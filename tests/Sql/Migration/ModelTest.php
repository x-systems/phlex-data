<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Sql\Migration;

use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Phlex\Data\Model;

class ModelTest extends \Phlex\Data\Tests\Sql\TestCase
{
    /**
     * @doesNotPerformAssertions
     */
    public function testSetModelCreate()
    {
        $this->dropTableIfExists('user');
        $user = new TestUser($this->db);

        $this->createMigrator($user)->create();

        // now we can use user
        $user->save(['name' => 'john', 'is_admin' => true, 'notes' => 'some long notes']);
    }

    public function testImportTable()
    {
        $this->assertTrue(true);

        return; // TODO enable once import to Model is supported using DBAL
        // @phpstan-ignore-next-line
        $this->dropTableIfExists('user');

        $migrator = $this->createMigrator();

        $migrator->table('user')->id()
            ->field('foo')
            ->field('str', ['type' => 'string'])
            ->field('bool', ['type' => 'boolean'])
            ->field('int', ['type' => 'integer'])
            ->field('mon', ['type' => 'money'])
            ->field('flt', ['type' => 'float'])
            ->field('date', ['type' => 'date'])
            ->field('datetime', ['type' => 'datetime'])
            ->field('time', ['type' => 'time'])
            ->field('txt', ['type' => 'text'])
            ->field('arr', ['type' => 'array'])
            ->field('obj', ['type' => 'object'])
            ->create();

        $this->db->dsql()->table('user')
            ->set([
                'id' => 1,
                'foo' => 'quite short value, max 255 characters',
                'str' => 'quite short value, max 255 characters',
                'bool' => true,
                'int' => 123,
                'mon' => 123.45,
                'flt' => 123.456789,
                'date' => (new \DateTime())->format('Y-m-d'),
                'datetime' => (new \DateTime())->format('Y-m-d H:i:s'),
                'time' => (new \DateTime())->format('H:i:s'),
                'txt' => 'very long text value' . str_repeat('-=#', 1000), // 3000+ chars
                'arr' => 'very long text value' . str_repeat('-=#', 1000), // 3000+ chars
                'obj' => 'very long text value' . str_repeat('-=#', 1000), // 3000+ chars
            ])->insert();

        $migrator2 = $this->createMigrator();
        $migrator2->importTable('user');

        $migrator2->mode('create');

        $q1 = preg_replace('/\([0-9,]*\)/i', '', $migrator->render()); // remove parenthesis otherwise we can't differ money from float etc.
        $q2 = preg_replace('/\([0-9,]*\)/i', '', $migrator2->render());
        $this->assertSame($q1, $q2);
    }

//     /**
//      * @doesNotPerformAssertions
//      */
//     public function testMigrateTable()
//     {
//         $this->dropTableIfExists('user');
//         $migrator = $this->createMigrator();
//         $migrator->table('user')->id()
//             ->field('foo')
//             ->field('bar', ['type' => 'integer'])
//             ->field('baz', ['type' => 'text'])
//             ->create();
//         $this->db->dsql()->table('user')
//             ->set([
//                 'id' => 1,
//                 'foo' => 'foovalue',
//                 'bar' => 123,
//                 'baz' => 'long text value',
//             ])->insert();
//     }

    public function testCreateModel()
    {
        $this->assertTrue(true);

        return; // TODO enable once create from Model is supported using DBAL
        // @phpstan-ignore-next-line
        $this->dropTableIfExists('user');

        $this->createMigrator(new TestUser($this->db))->create();

        $user_model = $this->createMigrator()->createModel($this->db, 'user');

        $this->assertSame(
            [
                'name',
                'password',
                'is_admin',
                'notes',
                'main_role_id', // our_field here not role_id (reference name)
            ],
            array_keys($user_model->getFields())
        );
    }

    public function testStringFieldCaseInsensitive()
    {
        $this->dropTableIfExists('user');

        if ($this->getDatabasePlatform() instanceof SQLServer2012Platform) {
            $this->markTestIncomplete('TODO MSSQL: Implicit conversion from data type char to varbinary(max) is not allowed. Use the CONVERT function to run this query');
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            $this->markTestIncomplete('TODO Oracle: ORA-01465: invalid hex number');
        }

        $model = new Model($this->db, ['table' => 'user']);
        $model->addField('string');
        $model->addField('text', ['type' => 'text']);
        $model->addField('blob', ['type' => ['text', 'codec' => \Phlex\Data\Persistence\Sql\Codec\Blob::class]]);
        $model->setOrder('id');

        $model->migrate();

        $model->import([
            ['id' => 1, 'string' => 'MixedCase'],
            ['id' => 2, 'text' => 'MixedCase'],
            ['id' => 3, 'blob' => 'MixedCase'],
        ]);

        $model->addCondition(Model\Scope::createOr(
            ['string', 'MIXEDcase'],
            ['text', 'MIXEDcase'],
            ['blob', 'MIXEDcase'],
        ));

        if ($this->getDatabasePlatform() instanceof PostgreSQL94Platform) {
            $this->markTestIncomplete('PostgreSQL does not support case insensitive column types');
        }

        $this->assertSame([['id' => 1], ['id' => 2]], $model->export(['id']));
    }
}

class TestUser extends \Phlex\Data\Model
{
    public $table = 'user';

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->addField('name');
        $this->addField('password', ['type' => 'password']);
        $this->addField('is_admin', ['type' => 'boolean']);
        $this->addField('notes', ['type' => 'text']);

        $this->hasOne('role', ['theirModel' => [TestRole::class], 'ourKey' => 'main_role_id', 'theirKey' => 'id']);
    }
}

class TestRole extends \Phlex\Data\Model
{
    public $table = 'role';

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->addField('name');
        $this->withMany('Users', ['theirModel' => [TestUser::class], 'ourKey' => 'id', 'theirKey' => 'main_role_id']);
    }
}
