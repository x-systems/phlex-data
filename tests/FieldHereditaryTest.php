<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Model;
use Phlex\Data\Persistence;

class FieldHereditaryTest extends SQL\TestCase
{
    public function testDirty1()
    {
        $p = new Persistence\Static_(['hello', 'world']);

        // default title field
        $m = new Model($p);
        $m->addExpression('caps', function ($m) {
            return strtoupper($m->get('name'));
        });

        $m->load(1);
        $this->assertSame('world', $m->get('name'));
        $this->assertSame('WORLD', $m->get('caps'));
    }
}
