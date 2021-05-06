<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Model;

class DefaultTest extends Sql\TestCase
{
    public function testDefaultValue()
    {
        $m = new Model();
        $m->addField('nodefault');
        $m->addField('withdefault', ['default' => 'abc']);

        $this->assertNull($m->get('nodefault'));
        $this->assertSame('abc', $m->get('withdefault'));

        $this->assertNull($m->getField('nodefault')->get());
        $this->assertSame('abc', $m->getField('withdefault')->get());
    }
}
