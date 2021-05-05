<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\SQL\Codec;

use Doctrine\DBAL\Types\Types;

class Date extends DateTime
{
    protected $columnTypeName = Types::DATE_MUTABLE;

    protected $format = 'Y-m-d';

    protected $timezone = false;

    protected function doEncode($value)
    {
        $this->timezone = false;

        return parent::doEncode($value);
    }

    protected function doDecode($value)
    {
        $this->timezone = false;

        return parent::doDecode($value);
    }
}
