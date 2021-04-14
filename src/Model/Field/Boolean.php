<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field;

/**
 * Your favorite nullable binary type.
 */
class Boolean extends \Phlex\Data\Model\Field
{
    /** @var string Field type for backward compatibility. */
    public $type = 'boolean';

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

    /**
     * Backward compatible way to specify value for true / false:.
     *
     * $enum = ['N', 'Y']
     *
     * @var array
     */
    public $enum;

    protected static $seedProperties = [
        'valueTrue',
        'valueFalse',
    ];

    /**
     * Constructor.
     */
    protected function init(): void
    {
        parent::init();

        // Backwards compatibility
        if ($this->enum) {
            $this->valueFalse = $this->enum[0];
            $this->valueTrue = $this->enum[1];
            //unset($this->enum);
        }
    }

    /**
     * Validate and normalize value.
     *
     * @param mixed $value
     *
     * @return mixed
     */
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

        throw new ValidationException([$this->name => 'Must be a boolean value']);
    }

    /**
     * Casts field value to string.
     *
     * @param mixed $value Optional value
     */
    public function toString($value = null): string
    {
        $v = ($value === null ? $this->get() : $this->normalize($value));

        return $v === true ? '1' : '0';
    }
}
