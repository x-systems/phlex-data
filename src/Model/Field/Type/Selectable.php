<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

class Selectable extends \Phlex\Data\Model\Field\Type
{
    /**
     * For fields that can be selected, values can represent interpretation of the values,
     * for instance ['F'=>'Female', 'M'=>'Male'];.
     *
     * @var array|null
     */
    public $values;

    protected $labels;

    public $allowMultipleSelection = false;

    protected function doNormalize($value)
    {
        if (!$this->values) {
            throw new ValidationException('Field has no associated values');
        }

        if (!$this->allowMultipleSelection && is_array($value)) {
            throw new ValidationException('Field accepts only single value');
        }

        $value = (array) $value;

        if (array_udiff($value, $this->values, function ($v1, $v2) {
            return $v1 === $v2 ? 0 : 1;
        })) {
            throw new ValidationException('Must be one of the associated values');
        }

        return $this->allowMultipleSelection ? $value : reset($value);
    }

    public function setValuesWithLabels(array $values)
    {
        $this->values = array_keys($values);

        $this->labels = $values;

        return $this;
    }

    public function toString($value): ?string
    {
        return json_encode($this->normalize($value));
    }
}
