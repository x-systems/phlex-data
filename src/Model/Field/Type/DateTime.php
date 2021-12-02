<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

/**
 * Basic datetime field type. Think of it as field type "datetime" in past.
 */
class DateTime extends \Phlex\Data\Model\Field\Type
{
    /**
     * DateTime class used for type = 'data', 'datetime', 'time' fields.
     *
     * For example, 'DateTime', 'Carbon' etc.
     *
     * @var string|null
     */
    public $dateTimeClass = \DateTime::class;

    /**
     * Timezone class used for type = 'data', 'datetime', 'time' fields.
     *
     * For example, 'DateTimeZone', 'Carbon' etc.
     *
     * @var string|null
     */
    public $dateTimeZoneClass = \DateTimeZone::class;

    protected function doNormalize($value)
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
                    throw (new ValidationException('Value must be a ' . static::class))
                        ->addMoreInfo('class', $class)
                        ->addMoreInfo('value class', get_class($value));
                }

                throw (new ValidationException('Value must be a ' . static::class))
                    ->addMoreInfo('class', $class)
                    ->addMoreInfo('value type', gettype($value));
            }
        }

        return $value;
    }

    public function compare($value1, $value2): bool
    {
        return $this->compareAsString($value1, $value2);
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

        return (string) $datetime;
    }
}
