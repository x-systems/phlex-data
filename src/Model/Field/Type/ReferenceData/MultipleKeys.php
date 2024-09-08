<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type\ReferenceData;

use Phlex\Data\Model\Field\Type;

class MultipleKeys extends Type\Selectable implements Type\ReferenceData
{
    use Type\ReferenceDataTrait;

    public $allowMultipleSelection = true;
}
