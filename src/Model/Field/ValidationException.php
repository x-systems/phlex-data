<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field;

class ValidationException extends \Phlex\Data\Exception
{
    public function __construct(array $failures)
    {
        parent::__construct('Field validation failed');

        foreach ($failures as $fieldName => $message) {
            $this->addMoreInfo($fieldName, $message);
        }
    }
}
