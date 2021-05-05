<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\SQL\Codec;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Types;
use Phlex\Data\Persistence\SQL;

class String_ extends SQL\Codec
{
    protected $columnTypeName = Types::STRING;

    public function decode($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return $this->doDecode($value);
    }

    public function migrate(SQL\Migration $migrator): Column
    {
        $column = parent::migrate($migrator);

        if ($maxLength = $this->getPersistenceValueType()->maxLength ?? null) {
            $column->setLength($maxLength);
        }

        return $column;
    }
}
