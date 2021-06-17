<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence;

use Phlex\Data\Model;
use Phlex\Data\Model\Scope;

class Codec extends Model\Field\Codec
{
    /**
     * Get field value type after serialization.
     */
    public function getValueType(): Model\Field\Type
    {
        return $this->field->getSerializedValueType();
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
}
