<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

class Time extends DateTime
{
    public function normalize($value)
    {
        $value = parent::normalize($value);

        if ($value !== null) {
            // remove date portion from date type value
            // need 1970 in place of 0 - DB
            $value = (clone $value)->setDate(1970, 1, 1);
        }

        return $value;
    }

    public function toString($value): ?string
    {
        $value = $this->normalize($value);

        return $value ? $value->format('H:i:s' . ($value->format('u') > 0 ? '.u' : '')) : '';
    }
}
