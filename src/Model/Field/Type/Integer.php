<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

/**
 * Integer field type.
 */
class Integer extends Numeric
{
    /**
     * @var int specify how many decimal numbers should be saved
     */
    public $decimals = 0;

    /**
     * @var bool Enable number rounding. If true will round number, otherwise will round it down (trim).
     */
    public $round = false;

    public function normalize($value)
    {
        $value = parent::normalize($value);

        return $value === null || $value === '' ? null : (int) $value;
    }
}
