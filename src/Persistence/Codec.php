<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence;

use Phlex\Data\Model;
use Phlex\Data\Model\Scope;

class Codec extends Model\Field\Codec
{
    public function encode($value)
    {
        // check null values for mandatory fields
        if ($value === null && $this->field->mandatory) {
            throw new Model\Field\ValidationException([
                $this->field->short_name => 'Mandatory field value cannot be null',
            ], $this->field->getOwner());
        }

        return parent::encode($value);
    }

    /**
     * Get field value type after serialization.
     */
    public function getValueType(): Model\Field\Type
    {
        return $this->field->getSerializedValueType($this->mutator);
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

    public function getKey(): string
    {
        return $this->field->actual ?? $this->field->short_name;
    }
}
