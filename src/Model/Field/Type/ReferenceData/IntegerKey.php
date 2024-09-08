<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type\ReferenceData;

use Phlex\Data\Model\Field\Type;

class IntegerKey extends Type\Integer implements Type\ReferenceData
{
    use Type\ReferenceDataTrait;
}
