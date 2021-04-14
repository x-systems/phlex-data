<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field;

/**
 * Time field type. Think of it as field type "time" in past.
 */
class Time extends DateTime
{
    /** @var string Field type for backward compatibility. */
    public $type = 'time';

    /**
     * Validate and normalize value.
     *
     * @param mixed $value
     *
     * @return mixed
     */
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

    /**
     * Casts field value to string.
     *
     * @param mixed $value Optional value
     */
    public function toString($value = null): string
    {
        $v = ($value === null ? $this->get() : $this->normalize($value));

        return $v ? $v->format('H:i:s' . ($v->format('u') > 0 ? '.u' : '')) : $v;
    }
}
