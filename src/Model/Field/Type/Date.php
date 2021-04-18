<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

class Date extends DateTime
{
    public function normalize($value)
    {
        $value = parent::normalize($value);

        if ($value !== null) {
            // remove time portion from date type value
            $value = (clone $value)->setTime(0, 0, 0);
        }

        return $value;
    }

    public function toString($value): ?string
    {
        $date = $this->normalize($value);

        return $date ? $date->format('Y-m-d') : '';
    }
}
