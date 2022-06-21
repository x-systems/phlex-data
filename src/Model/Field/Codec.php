<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field;

use Phlex\Core\InjectableTrait;
use Phlex\Data;
use Phlex\Data\Model;
use Phlex\Data\Model\Scope;

class Codec implements CodecInterface
{
    use InjectableTrait;

    /** @var Data\MutatorInterface */
    protected $mutator;

    /** @var Model\Field */
    protected $field;

    /** @var Model\Field\Serializer|string|array|null */
    protected $serialize;

    public function __construct(Data\MutatorInterface $mutator, Model\Field $field)
    {
        $this->mutator = $mutator;
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

        if ($this->serialize) {
            return $this->serialize($value);
        }

        return $this->doEncode($value);
    }

    public function decode($value)
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($this->serialize) {
            return $this->unserialize($value);
        }

        return $this->doDecode($value);
    }

    protected function isEncodable($value): bool
    {
        return $value !== null;
    }

    public function getSerializer(): ?Serializer
    {
        if ($this->serialize && !is_object($this->serialize)) {
            $this->serialize = Serializer::resolve($this->serialize);
        }

        return $this->serialize;
    }

    protected function serialize($value)
    {
        if (!$serializer = $this->getSerializer()) {
            return $value;
        }

        return $serializer->encode($value);
    }

    protected function unserialize($value)
    {
        if (!$serializer = $this->getSerializer()) {
            return $value;
        }

        return $serializer->decode($value);
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

    /**
     * Get field source value type.
     */
    public function getValueType(): Model\Field\Type
    {
        return $this->field->getValueType();
    }

    /**
     * Get the key under which to convert the field value.
     */
    public function getKey(): string
    {
        return $this->field->getKey();
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
                $value = array_map(fn ($option) => $this->encode($option), $value);
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
        return json_decode($json, $assoc, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * JSON encoding with proper error treatment.
     *
     * @param mixed $value
     */
    public static function jsonEncode($value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR, 512);
    }
}
