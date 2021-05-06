<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence;

use Phlex\Core\DiContainerTrait;
use Phlex\Data\Model;
use Phlex\Data\Model\Scope;

class Codec
{
    use DiContainerTrait;

    /** @var Model\Field */
    protected $field;

    public function __construct(Model\Field $field)
    {
        $this->field = $field;
    }

    public function encode($value)
    {
        if (!$this->isEncodable($value)) {
            return $value;
        }

        // we use clones of the object for encoding
        if (is_object($value)) {
            $value = clone $value;
        }

        return $this->doEncode($value);
    }

    public function decode($value)
    {
        if ($value === null || $value === '') {
            return;
        }

        return $this->doDecode($value);
    }

    protected function isEncodable($value): bool
    {
        return $value !== null;
    }

    protected function doEncode($value)
    {
        return $value;
    }

    protected function doDecode($value)
    {
        return $this->field->normalize($value);
    }

    public function getField(): Model\Field
    {
        return $this->field;
    }

    public function getPersistenceValueType(): Model\Field\Type
    {
        return $this->field->getPersistenceValueType();
    }

    public function getQueryArguments($operator, $value): array
    {
        $skipValueEncoding = [
            Scope\Condition::OPERATOR_LIKE,
            Scope\Condition::OPERATOR_NOT_LIKE,
            Scope\Condition::OPERATOR_REGEXP,
            Scope\Condition::OPERATOR_NOT_REGEXP,
        ];

        if ($this->isEncodable($value) && !in_array($operator, $skipValueEncoding, true)) {
            if (is_array($value)) {
                $value = array_map(function ($option) {
                    return $this->encode($option);
                }, $value);
            } else {
                $value = $this->encode($value);
            }
        }

        return [$this->field, $operator, $value];
    }

    /**
     * JSON decoding with proper error treatment.
     *
     * @return mixed
     */
    public static function jsonDecode(string $json, bool $assoc = true)
    {
        return json_decode($json, $assoc, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * JSON encoding with proper error treatment.
     *
     * @param mixed $value
     */
    public static function jsonEncode($value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR, 512);
    }
}
