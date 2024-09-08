<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

use Phlex\Data\Model;

trait ReferenceDataTrait
{
    /** @var Model\Field\Reference */
    protected $reference;

    public function getReference(): Model\Field\Reference
    {
        return $this->reference;
    }
}
