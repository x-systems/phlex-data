<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Model;

class ConditionTest extends \Phlex\Core\PHPUnit\TestCase
{
    public function testException1()
    {
        // not existing field in condition
        $m = new Model();
        $m->addField('name');

        $this->expectException(\Phlex\Core\Exception::class);
        $m->addCondition('last_name', 'Smith');
    }

    public function testBasicDiscrimination(): void
    {
        $m = new Model();
        $m->addField('name');

        $m->addField('gender', ['type' => ['enum', 'values' => ['M', 'F']]]);
        $m->addField('foo');

        $m->addCondition('gender', 'M');

        $this->assertCount(1, $m->scope()->getNestedConditions());

        $m->addCondition('gender', 'F');

        $this->assertCount(2, $m->scope()->getNestedConditions());
    }

    public function testEditableAfterCondition(): void
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('gender');

        $m->addCondition('gender', 'M');

        $this->assertTrue($m->getField('gender')->system);
        $this->assertFalse($m->getField('gender')->isEditable());
    }

    public function testEditableHasOne(): void
    {
        $gender = new Model();
        $gender->addField('name');

        $m = new Model();
        $m->addField('name');
        $m->hasOne('gender', ['theirModel' => $gender]);

        $this->assertTrue($m->getField('gender_id')->system);
        $this->assertFalse($m->getField('gender_id')->isEditable());
    }
}
