<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Oracle;

use Phlex\Data\Persistence;

/**
 * Class to perform queries on Oracle persistence.
 *
 * @property Persistence\Sql\Platform\Oracle $persistence
 */
class Query extends Persistence\Sql\Query
{
    public function doGetRows(): array
    {
        return array_map(function ($row) {
            unset($row['__phlex_rownum']);

            return $row;
        }, parent::doGetRows());
    }

    protected function doGetRow(): ?array
    {
        $row = parent::doGetRow();

        if ($row !== null) {
            unset($row['__phlex_rownum']);
        }

        return $row;
    }

    public function getIterator(): \Traversable
    {
        foreach ($this->execute()->iterateAssociative() as $row) {
            if ($row !== null) {
                unset($row['__phlex_rownum']);
            }

            yield $row;
        }
    }
}
