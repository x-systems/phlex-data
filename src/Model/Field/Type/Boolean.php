<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

class Boolean extends \Phlex\Data\Model\Field\Type
{
    /**
     * Value which will be used for "true".
     *
     * @var mixed
     */
    public $valueTrue = true;

    /**
     * Value which will be used for "false".
     *
     * @var mixed
     */
    public $valueFalse = false;

    public function normalize($value)
    {
        if ($value === null || $value === '') {
            return;
        }

        if (is_bool($value)) {
            return $value;
        }

        if ($value === $this->valueTrue) {
            return true;
        }

        if ($value === $this->valueFalse) {
            return false;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        throw new ValidationException('Must be a boolean value');
    }

    public function toString($value): ?string
    {
        $value = $this->normalize($value);

        return $value === $this->valueTrue ? '1' : '0';
    }
}
