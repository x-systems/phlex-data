<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\SQL\Platform;

use Phlex\Data\Model;
use Phlex\Data\Persistence;

class PostgreSQL extends Persistence\SQL
{
    protected function getIdSequenceName(Model $model): ?string
    {
        $sequenceName = parent::getIdSequenceName($model);

        if ($sequenceName === null) {
            // PostgreSQL uses sequence internally for PK autoincrement,
            // use default name if not set explicitly
            $sequenceName = $model->table . '_' . $model->primaryKey . '_seq';
        }

        return $sequenceName;
    }

    protected function syncIdSequence(Model $model): void
    {
        // PostgreSQL sequence must be manually synchronized if a row with explicit ID was inserted
        $this->connection->expr(
            'select setval([], coalesce(max({}), 0) + 1, false) from {}',
            [$this->getIdSequenceName($model), $model->primaryKey, $model->table]
        )->execute();
    }
}
