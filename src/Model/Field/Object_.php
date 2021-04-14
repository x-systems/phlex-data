<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field;

use Phlex\Data\Model\Field;

/**
 * Object field type. Think of it as field type "object" in past.
 */
class Object_ extends Field
{
    /** @var string Field type for backward compatibility. */
    public $type = 'object';

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
                $value = $persistence->jsonDecode($this, $value, false);
            }
        }

        if (!is_object($value)) {
            throw new ValidationException([$this->name => 'Must be an object']);
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
