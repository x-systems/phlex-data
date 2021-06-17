<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

use Doctrine\DBAL\Types\Types;
use Phlex\Data\Persistence\Sql;

/**
 * @method \Phlex\Data\Model\Field\Type\Boolean getValueType()
 */
class Boolean extends Sql\Codec
{
    protected $columnTypeName = Types::BOOLEAN;

    protected function doEncode($value)
    {
        // if enum is set, first lets see if it matches one of those precisely
        if ($value === $this->getValueType()->valueTrue) {
            $value = true;
        } elseif ($value === $this->getValueType()->valueFalse) {
            $value = false;
        }

        return (bool) $value;
    }
}
