<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field;

/**
 * Basic string field type. Think of it as field type "string" in past.
 * This do not allow you to have multiple paragrahs (restrict line-ends).
 *
 * Most of the time this is most basic type you will use.
 */
class Line extends Text
{
    /** @var string Field type for backward compatibility. */
    public $type = 'string';

    /**
     * @var int specify a maximum length for this text
     */
    public $max_length = 255;

    protected static $seedProperties = [
        'max_length',
    ];

    /**
     * Validate and normalize value.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function normalize($value)
    {
        // remove all line-ends
        return trim(str_replace(["\r", "\n"], '', parent::normalize($value)));
    }
}
