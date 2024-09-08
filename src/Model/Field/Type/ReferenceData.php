<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

use Phlex\Data\Model;

interface ReferenceData
{
    public function getReference(): Model\Field\Reference;
}
