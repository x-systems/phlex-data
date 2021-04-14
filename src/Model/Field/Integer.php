<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field;

/**
 * Integer field type.
 */
class Integer extends Numeric
{
    /** @var string Field type for backward compatibility. */
    public $type = 'integer';

    /**
     * @var int specify how many decimal numbers should be saved
     */
    public $decimals = 0;

    /**
     * @var bool Enable number rounding. If true will round number, otherwise will round it down (trim).
     */
    public $round = false;

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

        return $value === null ? null : (int) $value;
    }
}
