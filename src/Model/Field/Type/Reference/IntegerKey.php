<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type\Reference;

use Phlex\Data\Model\Field\Type;

class IntegerKey extends Type\Integer implements Type\ReferenceInterface
{
    use Type\ReferenceTrait;
}
