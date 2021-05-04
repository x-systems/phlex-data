<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Types;
use Phlex\Data\Persistence\Sql;

class String_ extends Sql\Codec
{
    protected $columnTypeName = Types::STRING;

    public function decode($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return $this->doDecode($value);
    }

    public function migrate(Sql\Migration $migrator): Column
    {
        $column = parent::migrate($migrator);

        if ($this->getPersistenceValueType()->maxLength ?? null) {
            $column->setLength($this->getPersistenceValueType()->maxLength);
        }

        return $column;
    }
}
