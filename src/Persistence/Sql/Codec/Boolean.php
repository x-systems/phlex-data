<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

class Boolean extends Integer
{
    public function encode($value)
    {
        // if enum is set, first lets see if it matches one of those precisely
        if ($value === $this->getFieldType()->valueTrue) {
            $value = true;
        } elseif ($value === $this->getFieldType()->valueFalse) {
            $value = false;
        }

        // finally, convert into appropriate value
        return $value ? 1 : 0;
    }

    public function decode($value)
    {
        if ($value === null) {
            return;
        }

        return $value ? $this->getFieldType()->valueTrue : $this->getFieldType()->valueFalse;
    }
}
