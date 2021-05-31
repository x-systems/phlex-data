<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

class Array_ extends \Phlex\Data\Model\Field\Type
{
    protected function doNormalize($value)
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true);
            } catch (\Exception $e) {
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
