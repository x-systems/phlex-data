<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Types;
use Phlex\Data\Persistence\Sql;

class Float_ extends Sql\Codec
{
    protected $columnTypeName = Types::FLOAT;

    protected function doEncode($value)
    {
        return (float) $value;
    }

    public function migrate(Sql\Migration $migrator): Column
    {
        return parent::migrate($migrator)->setUnsigned(true);
    }
}
