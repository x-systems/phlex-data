<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

class Text extends \Phlex\Data\Model\Field\Type
{
    /**
     * @var int specify a maximum length for this text
     */
    public $maxLength;

    public function normalize($value)
    {
        $this->assertScalar($value);

        if ($value === null) {
            return;
        }

        $value = (string) $value;

        if ($this->maxLength && mb_strlen($value) > $this->maxLength) {
            throw new ValidationException('Must be not longer than ' . $this->maxLength . ' symbols');
        }

        // normalize line-ends to LF and trim
        return trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
    }
}
