<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Model;

class Folder extends Model
{
    public $table = 'folder';

    protected function doInitialize(): void
    {
        parent::doInitialize();
        $this->addField('name');

        $this->hasMany('SubFolder', ['model' => [self::class], 'theirFieldName' => 'parent_id'])
            ->addField('count', ['aggregate' => 'count', 'field' => $this->persistence->expr('*')]);

        $this->hasOne('parent_id', ['model' => [self::class]])
            ->addTitle();

        $this->addField('is_deleted', ['type' => 'boolean']);
        $this->addCondition('is_deleted', false);
    }
}

class FolderTest extends Sql\TestCase
{
    public function testRate()
    {
        $this->setDb([
            'folder' => [
                ['parent_id' => 1, 'is_deleted' => 0, 'name' => 'Desktop'],
                ['parent_id' => 1, 'is_deleted' => 0, 'name' => 'My Documents'],
                ['parent_id' => 1, 'is_deleted' => 0, 'name' => 'My Videos'],
                ['parent_id' => 1, 'is_deleted' => 0, 'name' => 'My Projects'],
                ['parent_id' => 4, 'is_deleted' => 0, 'name' => 'Agile Data'],
                ['parent_id' => 4, 'is_deleted' => 0, 'name' => 'DSQL'],
                ['parent_id' => 4, 'is_deleted' => 0, 'name' => 'Agile Toolkit'],
                ['parent_id' => 4, 'is_deleted' => 1, 'name' => 'test-project'],
            ],
        ]);

        $f = new Folder($this->db);
        $f->load(4);

        $this->assertEquals([
            'id' => 4,
            'name' => 'My Projects',
            'count' => 3,
            'parent_id' => 1,
            'parent' => 'Desktop',
            'is_deleted' => 0,
        ], $f->get());
    }
}
