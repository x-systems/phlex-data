<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform;

use Phlex\Data\Persistence;

class Oracle extends Persistence\Sql
{
    public $_default_seed_migration = [Oracle\Migration::class];
}
