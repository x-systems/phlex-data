<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

use Phlex\Data\Model\Field;

class Enum extends Field\Type
{
    public $values = [];
    
    protected function doNormalize($value)
    {
        $value = parent::doNormalize($value);
        
        if ($value !== null && !in_array($value, $this->values, true)) {
            throw (new ValidationException('This is not one of the allowed values for the field'))
                ->addMoreInfo('values', $this->values);
        }
        
        return $value;
    }
}
