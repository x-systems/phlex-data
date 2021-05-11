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
    public const MODE_SELECT_LIMIT = 'select_limit';

    protected static $templates = [
        self::MODE_SELECT => '[with]select[option] [field] [from] [table][join][where][group][having][order]',
        self::MODE_SELECT_LIMIT => 'select * from (select "__t".*, rownum "__dsql_rownum" [from] ([with]select[option] [field] [from] [table][join][where][group][having][order]) "__t") where "__dsql_rownum" > [limit_start][and_limit_end]',
    ];

    public function doGetRows(): array
    {
        return array_map(function ($row) {
            unset($row['__dsql_rownum']);

            return $row;
        }, parent::doGetRows());
    }

    protected function doGetRow(): ?array
    {
        $row = parent::doGetRow();

        if ($row !== null) {
            unset($row['__dsql_rownum']);
        }

        return $row;
    }

    public function getIterator(): \Traversable
    {
        return (function ($iterator) {
            foreach ($iterator as $row) {
                if ($row !== null) {
                    unset($row['__dsql_rownum']);
                }

                yield $row;
            }
        })($this->execute()->iterateAssociative());
    }

    protected function initExists()
    {
        $this->statement = $this->persistence->statement()->select()->field(
            $this->persistence->expr('case when exists[] then 1 else 0 end', [$this->statement])
        );
    }
}
