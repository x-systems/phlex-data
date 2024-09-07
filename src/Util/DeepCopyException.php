<?php

declare(strict_types=1);

namespace Phlex\Data\Util;

use Phlex\Data\Exception;

class DeepCopyException extends Exception
{
    public function addDepth(string $prefix)
    {
        $this->addMoreInfo('depth', $prefix . ':' . $this->getParams()['depth']);

        return $this;
    }
}
