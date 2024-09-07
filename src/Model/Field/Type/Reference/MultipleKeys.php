<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type\Reference;

use Phlex\Data\Model\Field\Type;

class MultipleKeys extends Type\Selectable implements Type\ReferenceInterface
{
    use Type\ReferenceTrait;

    public $allowMultipleSelection = true;
}
