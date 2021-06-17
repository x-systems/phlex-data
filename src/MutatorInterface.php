<?php

declare(strict_types=1);

namespace Phlex\Data;

interface MutatorInterface
{
    /**
     * Retrieve the active codecs for the data mutator object.
     */
    public function getCodecs();

    /**
     * Add custom codecs to data mutator.
     *
     * @return static
     */
    public function setCodecs(array $codecs);
}
