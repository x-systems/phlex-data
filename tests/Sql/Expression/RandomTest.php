<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Sql\Expression;

use Phlex\Core\PHPUnit;
use Phlex\Data\Persistence\Sql;

class RandomTest extends PHPUnit\TestCase
{
    public function q(...$args)
    {
        return new Sql\Statement(...$args);
    }
}
