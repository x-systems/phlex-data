<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

class Array_ extends \Phlex\Data\Model\Field\Type
{
    /**
     * For fields that can be selected, values can represent interpretation of the values,
     * for instance ['F'=>'Female', 'M'=>'Male'];.
     *
     * @var array|null
     */
    public $values;

    public function normalize($value)
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

        if (array_diff($value, array_keys($this->values))) {
            throw new ValidationException('Must be one of the associated values');
        }

        return $value;
    }

    public function toString($value): ?string
    {
        return json_encode($this->normalize($value));
    }
}
