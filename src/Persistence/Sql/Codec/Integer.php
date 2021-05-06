<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Types;
use Phlex\Data\Persistence\Sql;

class Integer extends Sql\Codec
{
    protected $columnTypeName = Types::INTEGER;

    protected function doEncode($value)
    {
        return (int) $value;
    }

    public function migrate(Sql\Migration $migrator): Column
    {
        $column = parent::migrate($migrator);

        if ($this->field->getReference()) {
            $column->setUnsigned(true);
        }

        return $column;
    }
}
