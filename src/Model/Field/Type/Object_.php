<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

/**
 * Object field type. Think of it as field type "object" in past.
 */
class Object_ extends \Phlex\Data\Model\Field\Type
{
    public function normalize($value)
    {
        if ($value === null || $value === '') {
            return;
        }

        if (is_string($value)) {
            try {
                $value = json_decode($value, false);
            } catch (\Exception $e) {
                throw new ValidationException('Value cannot be normalized');
            }
        }

        if (!is_object($value)) {
            throw new ValidationException('Must be an object');
        }

        return $value;
    }

    public function compare($value1, $value2): bool
    {
        return $this->compareAsString($value1, $value2);
    }

    public function toString($value): ?string
    {
        return json_encode($this->normalize($value));
    }
}
