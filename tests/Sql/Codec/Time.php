<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\SQL\Codec;

class Time extends Date
{
    protected $format = 'H:i:s.u';
}
