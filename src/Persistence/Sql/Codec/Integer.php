<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Types;
use Phlex\Data\Persistence\Sql;

class Integer extends Sql\Codec
{
    protected $columnTypeName = Types::INTEGER;

    public function encode($value)
    {
        return (int) $value;
    }

    public function decode($value)
    {
        return (int) $value;
    }

    public function migrate(Sql\Migration $migrator): Column
    {
        return parent::migrate($migrator)->setUnsigned(true);
    }
}
