<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\SQL\Codec;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Types;
use Phlex\Data\Persistence\SQL;

class Integer extends SQL\Codec
{
    protected $columnTypeName = Types::INTEGER;

    protected function doEncode($value)
    {
        return (int) $value;
    }

    public function migrate(SQL\Migration $migrator): Column
    {
        $column = parent::migrate($migrator);

        if ($this->field->getReference()) {
            $column->setUnsigned(true);
        }

        return $column;
    }
}
