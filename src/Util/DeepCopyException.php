<?php

declare(strict_types=1);

namespace Phlex\Data\Util;

class DeepCopyException extends \Phlex\Data\Exception
{
    public function addDepth(string $prefix)
    {
        $this->addMoreInfo('depth', $prefix . ':' . $this->getParams()['depth']);

        return $this;
    }
}
