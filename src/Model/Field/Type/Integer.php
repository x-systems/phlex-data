<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

/**
 * Integer field type.
 */
class Integer extends Float_
{
    /**
     * @var int specify how many decimal numbers should be saved
     */
    public $decimals = 0;

    /**
     * @var bool Enable number rounding. If true will round number, otherwise will round it down (trim).
     */
    public $round = false;

    protected function doNormalize($value)
    {
        return (int) parent::doNormalize($value);
    }
}
