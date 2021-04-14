<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field;

use Phlex\Data\Model\Field;

/**
 * Basic datetime field type. Think of it as field type "datetime" in past.
 */
class DateTime extends Field
{
    /** @var string Field type for backward compatibility. */
    public $type = 'datetime';

    /**
     * Array with Persistence settings like format, timezone etc.
     * It's job of Persistence to take these settings into account if needed.
     *
     * @var array
     */
    public $persistence = [
        'format' => null, // for date it can be 'Y-m-d', for datetime - 'Y-m-d H:i:s' etc.
        'timezone' => 'UTC', // 'IST', 'UTC', 'Europe/Riga' etc.
    ];

    /**
     * DateTime class used for type = 'data', 'datetime', 'time' fields.
     *
     * For example, 'DateTime', 'Carbon' etc.
     *
     * @param string
     */
    public $dateTimeClass = \DateTime::class;

    /**
     * Timezone class used for type = 'data', 'datetime', 'time' fields.
     *
     * For example, 'DateTimeZone', 'Carbon' etc.
     *
     * @param string
     */
    public $dateTimeZoneClass = \DateTimeZone::class;

    protected static $seedProperties = [
        'dateTimeClass',
        'dateTimeZoneClass',
    ];

    /**
     * Validate and normalize value.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function normalize($value)
    {
        if ($value === null) {
            if ($this->mandatory || $this->required) {
                throw new ValidationException([$this->name => 'Must not be null']);
            }

            return;
        }

        if ($value === '') {
            if ($this->required) {
                throw new ValidationException([$this->name => 'Must not be empty']);
            }

            return;
        }

        // we allow http://php.net/manual/en/datetime.formats.relative.php
        $class = $this->dateTimeClass ?? \DateTime::class;

        if (is_numeric($value)) {
            $value = new $class('@' . $value);
        } elseif (is_string($value)) {
            $value = new $class($value);
        } elseif (!$value instanceof $class) {
            if ($value instanceof \DateTimeInterface) {
                $value = new $class($value->format('Y-m-d H:i:s.u'), $value->getTimezone());
            } else {
                if (is_object($value)) {
                    throw new ValidationException(['must be a ' . $this->type, 'class' => $class, 'value class' => get_class($value)]);
                }

                throw new ValidationException(['must be a ' . $this->type, 'class' => $class, 'value type' => gettype($value)]);
            }
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

        if ($v) {
            $dateFormat = 'Y-m-d';
            $timeFormat = 'H:i:s' . ($v->format('u') > 0 ? '.u' : '');

            $format = $dateFormat . '\T' . $timeFormat . 'P'; // ISO 8601 format 2004-02-12T15:19:21+00:00

            $v = $v->format($format);
        }

        return $v;
    }
}
