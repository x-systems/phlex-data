<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field;

use Phlex\Data\Model\Field;

/**
 * Array field type. Think of it as field type "array" in past.
 */
class Array_ extends Field
{
    /** @var string Field type for backward compatibility. */
    public $type = 'array';

    /**
     * Validate and normalize value.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function normalize($value)
    {
        if ($value === null || $value === '') {
            if ($this->required) {
                throw new ValidationException([$this->name => 'Must not be null or empty']);
            }

            return;
        }

        if (is_string($value)) {
            if ($persistence = $this->hasPersistence()) {
                $value = $persistence->jsonDecode($this, $value, true);
            }
        }

        if (!is_array($value)) {
            throw new ValidationException([$this->name => 'Must be an array']);
        }

        return $value;
    }

    /**
     * Casts field value to string.
     *
     * @param mixed $value Optional value
     */
    public function toString($value = null): string
    {
        $v = ($value === null ? $this->get() : $this->normalize($value));

        if ($persistence = $this->hasPersistence()) {
            $v = $persistence->jsonEncode($this, $v);
        } else {
            $v = json_encode($v);
        }

        return $v;
    }
}
