<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

use Phlex\Data\Model;

trait ReferenceDataTrait
{
    /** @var string */
    protected $referenceFieldKey;

    public function setReference($reference)
    {
        if ($reference instanceof Model\Field) {
            $reference = $reference->getKey();
        }

        $this->referenceFieldKey = $reference;

        return $this;
    }

    public function getReference(Model\Field $field): Model\Field\Reference
    {
        return $field->getOwner()->getReference($this->referenceFieldKey);
    }
}
