<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

use Phlex\Data\Model\Field\Type;

class Array_ extends Type
{
    protected function doNormalize($value)
    {
        if (is_string($value)) {
            $value = json_decode($value, true);

            if ($value === false) {
                throw new ValidationException('Value cannot be normalized');
            }
        }

        if (!is_array($value)) {
            throw new ValidationException('Must be an array');
        }

        return $value;
    }

    public function toString($value): ?string
    {
        return json_encode($this->normalize($value));
    }
}
