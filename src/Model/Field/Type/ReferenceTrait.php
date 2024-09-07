<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

use Phlex\Data\Model;

trait ReferenceTrait
{
    /** @var Model\Field */
    protected $reference;

    public function getReference(): Model\Field
    {
        return $this->reference;
    }
}
