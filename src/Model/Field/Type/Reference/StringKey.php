<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type\Reference;

use Phlex\Data\Model\Field\Type;

class StringKey extends Type\String_ implements Type\ReferenceInterface
{
    use Type\ReferenceTrait;
}
