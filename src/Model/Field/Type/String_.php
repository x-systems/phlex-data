<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

class String_ extends Text
{
    /**
     * @var int specify a maximum length for this text
     */
    public $maxLength = 255;

    public function normalize($value)
    {
        if ($value === null) {
            return;
        }

        // remove all line-ends
        return trim(str_replace(["\r", "\n"], '', parent::normalize($value)));
    }
}
