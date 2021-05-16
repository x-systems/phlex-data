<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Sql\Migration;

use Phlex\Data\Model;

class BasicTest extends \Phlex\Data\Tests\Sql\TestCase
{
    /**
     * Test constructor.
     *
     * @doesNotPerformAssertions
     */
    public function testCreate()
    {
        $this->migrate('user');
    }

    /**
     * Tests creating and dropping of tables.
     *
     * @doesNotPerformAssertions
     */
    public function testCreateAndDrop()
    {
        $this->migrate('user');

        $this->createMigrator()->table('user')->drop();
    }

    protected function migrate($tableName)
    {
        $this->dropTableIfExists($tableName);

        $model = new Model($this->db, ['table' => $tableName]);

        $model->addField('foo');
        $model->addField('bar', ['type' => 'integer']);
        $model->addField('baz', ['type' => 'text']);
        $model->addField('bl', ['type' => 'boolean']);
        $model->addField('tm', ['type' => 'time']);
        $model->addField('dt', ['type' => 'date']);
        $model->addField('dttm', ['type' => 'datetime']);
        $model->addField('fl', ['type' => 'float']);
        $model->addField('mn', ['type' => 'money']);

        $model->migrate();
    }
}
