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
    
    public $allowMultipleSelection = false;

    protected function doNormalize($value)
    {
//         if (is_string($value)) {
//             try {
//                 $value = json_decode($value, true);
//             } catch (\Exception $e) {               
//             }
//         }

        if ($this->values !== null) {
            $value = (array) $value;
            
            if (array_udiff($value, array_keys($this->values), function($v1, $v2) {
                return $v1 === $v2 ? 0 : 1;
            })) {
                throw new ValidationException('Must be one of the associated values');
            }
        }

        return $value;
    }

    public function toString($value): ?string
    {
        return json_encode($this->normalize($value));
    }
}
