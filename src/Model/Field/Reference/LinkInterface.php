<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Reference;

use Phlex\Data\Model;

interface LinkInterface
{
    /**
     * Creates model that can be used for generating sub-queries.
     */
    public function createTheirModelLinked(array $defaults = []): Model;
}
