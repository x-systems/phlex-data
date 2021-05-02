<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

class Time extends Date
{
    protected $format = 'H:i:s.u';
}
