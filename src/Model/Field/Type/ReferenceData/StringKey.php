<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type\ReferenceData;

use Phlex\Data\Model\Field\Type;

class StringKey extends Type\String_ implements Type\ReferenceData
{
    use Type\ReferenceDataTrait;
}
