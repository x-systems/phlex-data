<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\SQL\Codec;

use Doctrine\DBAL\Types\Types;
use Phlex\Data\Exception;
use Phlex\Data\Persistence\SQL;

class DateTime extends SQL\Codec
{
    protected $columnTypeName = Types::DATETIME_MUTABLE;

    protected $format = 'Y-m-d H:i:s.u';

    protected $timezone = 'UTC';

    protected function doEncode($value)
    {
        $dateTimeClass = $this->getPersistenceValueType()->dateTimeClass ?? \DateTime::class;
        $timeZoneClass = $this->getPersistenceValueType()->dateTimeZoneClass ?? \DateTimeZone::class;

        if ($value instanceof $dateTimeClass || $value instanceof \DateTimeInterface) {
            // datetime only - set to persisting timezone
            if ($this->timezone) {
                $value = new \DateTime($value->format($this->format), $value->getTimezone());
                $value->setTimezone(new $timeZoneClass($this->timezone));
            }
            $value = $value->format($this->format);
        }

        return $value;
    }

    protected function doDecode($value)
    {
        $dateTimeClass = $this->getPersistenceValueType()->dateTimeClass ?? \DateTime::class;
        $timeZoneClass = $this->getPersistenceValueType()->dateTimeZoneClass ?? \DateTimeZone::class;

        if (is_numeric($value)) {
            $valueDecoded = new $dateTimeClass('@' . $value);
        } elseif (is_string($value)) {
            // ! symbol in date format is essential here to remove time part of DateTime - don't remove, this is not a bug
            $format = '+!' . $this->format;
            if (strpos($value, '.') !== false) { // time possibly with microseconds, otherwise invalid format
                $format = preg_replace('~(?<=H:i:s)(?![. ]*u)~', '.u', $format);
            } else {
                $format = preg_replace('~(\.u)~', '', $format);
            }

            // datetime only - set from persisting timezone
            if ($this->timezone) {
                $valueDecoded = $dateTimeClass::createFromFormat($format, $value, new $timeZoneClass($this->timezone));
                if ($valueDecoded !== false) {
                    $valueDecoded->setTimezone(new $timeZoneClass(date_default_timezone_get()));
                }
            } else {
                $valueDecoded = $dateTimeClass::createFromFormat($format, $value);
            }

            if ($valueDecoded === false) {
                throw (new Exception('Incorrectly formatted date/time'))
                    ->addMoreInfo('format', $format)
                    ->addMoreInfo('value', $value)
                    ->addMoreInfo('field', $this->field);
            }

            // need to cast here because DateTime::createFromFormat returns DateTime object not $dt_class
            // this is what Carbon::instance(DateTime $dt) method does for example
            if ($dateTimeClass !== 'DateTime') {
                $valueDecoded = new $dateTimeClass($valueDecoded->format('Y-m-d H:i:s.u'), $valueDecoded->getTimezone());
            }
        }

        return $valueDecoded;
    }
}
