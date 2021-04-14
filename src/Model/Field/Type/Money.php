<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

/**
 * Class Money offers a lightweight implementation of currencies.
 */
class Money extends Numeric
{
    /**
     * @var int specify how many decimal numbers should be saved
     */
    public $decimals = 2;
}
