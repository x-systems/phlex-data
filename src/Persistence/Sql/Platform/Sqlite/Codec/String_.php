<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Sqlite\Codec;

use Doctrine\DBAL\Schema\Column;
use Phlex\Data\Persistence\Sql;

class String_ extends Sql\Codec\Text
{
    public function migrate(Sql\Migration $migrator): Column
    {
        return parent::migrate($migrator)->setPlatformOption('collation', 'NOCASE');
    }
}
