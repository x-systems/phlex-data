<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\SQL\Platform;

use Phlex\Data\Model;
use Phlex\Data\Persistence;

class Sqlite extends Persistence\SQL
{
    protected static $defaultCodecs = [
        [SQLite\Codec\String_::class],
        Model\Field\Type\String_::class => [SQLite\Codec\String_::class],
        Model\Field\Type\Text::class => [SQLite\Codec\Text::class],
    ];
}
