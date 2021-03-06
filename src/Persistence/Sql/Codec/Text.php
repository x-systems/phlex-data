<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

use Doctrine\DBAL\Types\Types;

class Text extends String_
{
    protected $columnTypeName = Types::TEXT;
}
