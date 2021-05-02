<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

use Doctrine\DBAL\Types\Types;

class Date extends DateTime
{
    protected $columnTypeName = Types::DATE_MUTABLE;

    protected $format = 'Y-m-d';

    protected $timezone = false;

    public function encode($value)
    {
        $this->timezone = false;

        return parent::encode($value);
    }

    public function decode($value)
    {
        $this->timezone = false;

        return parent::decode($value);
    }
}
