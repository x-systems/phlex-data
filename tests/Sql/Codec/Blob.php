<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\SQL\Codec;

use Doctrine\DBAL\Types\Types;

class Blob extends String_
{
    protected $columnTypeName = Types::BLOB;
}
