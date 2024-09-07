<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type\Reference;

use Phlex\Data\Model\Field\Type;

abstract class ContainedRecords extends Type\Array_ implements Type\ReferenceInterface
{
    use Type\ReferenceTrait;
}
