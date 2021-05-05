<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\SQL\Platform\SQLite\Codec;

use Doctrine\DBAL\Schema\Column;
use Phlex\Data\Persistence\SQL;

class Text extends SQL\Codec\Text
{
    public function migrate(SQL\Migration $migrator): Column
    {
        return parent::migrate($migrator)->setPlatformOption('collation', 'NOCASE');
    }
}
