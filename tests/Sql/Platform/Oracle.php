<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\SQL\Platform;

use Phlex\Data\Model;
use Phlex\Data\Persistence;

class Oracle extends Persistence\SQL
{
    public $_default_seed_migration = [Oracle\Migration::class];

    public function lastInsertId(Model $model = null): string
    {
        // TODO: Oracle does not support lastInsertId(), only for testing
        // as this does not support concurrent inserts
        if (!$model->hasPrimaryKeyField()) {
            return ''; // TODO code should never call lastInsertId() if id field is not defined
        }

        $query = $this->connection->dsql()->table($model->table);
        $query->field($query->expr('max({id_col})', ['id_col' => $model->primaryKey]), 'max_id');

        return $query->getOne();
    }
}
