<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

class Boolean extends Integer
{
    protected function doEncode($value)
    {
        // if enum is set, first lets see if it matches one of those precisely
        if ($value === $this->getPersistenceValueType()->valueTrue) {
            $value = true;
        } elseif ($value === $this->getPersistenceValueType()->valueFalse) {
            $value = false;
        }

        // finally, convert into appropriate value
        return $value ? 1 : 0;
    }
}
