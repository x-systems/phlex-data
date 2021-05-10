<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform;

use Phlex\Data\Model;
use Phlex\Data\Persistence;

class Sqlite extends Persistence\Sql
{
    protected static $defaultCodecs = [
        [Sqlite\Codec\String_::class],
        Model\Field\Type\String_::class => [Sqlite\Codec\String_::class],
        Model\Field\Type\Text::class => [Sqlite\Codec\Text::class],
    ];

    public $_default_seed_statement = [Sqlite\Statement::class];

    public function groupConcat($field, string $delimiter = ','): Persistence\Sql\Expression
    {
        return $this->expr('group_concat({}, [])', [$field, $delimiter]);
    }
}
