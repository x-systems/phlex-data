<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform;

use Phlex\Data\Model;
use Phlex\Data\Persistence;

class Sqlite extends Persistence\Sql
{
    protected $seeds = [
        Persistence\Sql\Statement::class => [Sqlite\Statement::class],
    ];

    protected $codecs = [
        [Sqlite\Codec\String_::class],
        Model\Field\Type\String_::class => [Sqlite\Codec\String_::class],
        Model\Field\Type\Text::class => [Sqlite\Codec\Text::class],
    ];
}
