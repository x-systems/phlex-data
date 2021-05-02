<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Types;
use Phlex\Data\Persistence\Sql;

class Float_ extends Sql\Codec
{
    protected $columnTypeName = Types::FLOAT;

    public function encode($value)
    {
        return (float) $value;
    }

    public function decode($value)
    {
        return (float) $value;
    }

    public function migrate(Sql\Migration $migrator): Column
    {
        return parent::migrate($migrator)->setUnsigned(true);
    }
}
