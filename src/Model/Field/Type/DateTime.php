<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

/**
 * Basic datetime field type. Think of it as field type "datetime" in past.
 */
class DateTime extends \Phlex\Data\Model\Field\Type
{
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

    public function normalize($value)
    {
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
                    throw new ValidationException(['must be a ' . static::class, 'class' => $class, 'value class' => get_class($value)]);
                }

                throw new ValidationException(['must be a ' . static::class, 'class' => $class, 'value type' => gettype($value)]);
            }
        }

        return $value;
    }

    public function compare($value1, $value2): bool
    {
        return $this->normalize($value1) === $this->normalize($value2);
    }

    public function toString($value): ?string
    {
        $datetime = $this->normalize($value);

        if ($datetime) {
            $dateFormat = 'Y-m-d';
            $timeFormat = 'H:i:s' . ($datetime->format('u') > 0 ? '.u' : '');

            $format = $dateFormat . '\T' . $timeFormat . 'P'; // ISO 8601 format 2004-02-12T15:19:21+00:00

            $datetime = $datetime->format($format);
        }

        return $datetime;
    }
}
