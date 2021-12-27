<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence;

use Phlex\Data\Model;

class Codec extends Model\Field\Codec
{
    public function encode($value)
    {
        // check null values for mandatory fields
        if ($value === null && $this->field->mandatory) {
            throw new Model\Field\ValidationException([
                $this->field->elementId => 'Mandatory field value cannot be null',
            ], $this->field->getOwner());
        }

        return parent::encode($value);
    }

    /**
     * Get field value type after serialization.
     */
    public function getValueType(): Model\Field\Type
    {
        return $this->field->getValueType();
    }

    public function getKey(): string
    {
        return $this->field->actual ?? $this->field->elementId;
    }
}
