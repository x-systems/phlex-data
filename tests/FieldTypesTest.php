<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Model;
use Phlex\Data\Persistence\Static_ as Persistence_Static;

/**
 * Test various Field.
 */
class FieldTypesTest extends SQL\TestCase
{
    public $pers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pers = new Persistence_Static([
            1 => ['name' => 'John'],
            2 => ['name' => 'Peter'],
        ]);
    }

    public function testFielTypeSeedResolving()
    {
        $this->assertSame([Model\Field\Type\String_::class], Model\Field\Type::resolve('string'));

        $this->assertSame([Model\Field\Type\Generic::class], Model\Field\Type::resolve(null));

        $this->assertSame([Model\Field\Type\String_::class, 'maxLength' => 5], Model\Field\Type::resolve(['string', 'maxLength' => 5]));
    }

    public function testEmailBasic()
    {
        $m = new Model($this->pers);
        $m->addField('email', ['type' => 'email']);

        // null value
        $m->set('email', null);
        $m->save();
        $this->assertNull($m->get('email'));

        // normal value
        $m->set('email', 'foo@example.com');
        $m->save();
        $this->assertSame('foo@example.com', $m->get('email'));

        // padding, spacing etc removed
        $m->set('email', " \t " . 'foo@example.com ' . " \n ");
        $m->save();
        $this->assertSame('foo@example.com', $m->get('email'));

        // no domain - go to hell :)
        $this->expectException(Model\Field\ValidationException::class);
        $this->expectExceptionMessage('Email format is invalid');
        $m->set('email', 'qq');
    }

    public function testEmailMultipleValues()
    {
        $m = new Model($this->pers);
        $m->addField('email', ['type' => 'email']);
        $m->addField('emails', ['type' => ['email', 'allow_multiple' => true]]);

        $m->set('emails', 'bar@exampe.com, foo@example.com');
        $this->assertSame('bar@exampe.com, foo@example.com', $m->get('emails'));

        $this->expectException(Model\Field\ValidationException::class);
        $this->expectExceptionMessage('a single email');
        $m->set('email', 'bar@exampe.com, foo@example.com');
    }

    public function testEmailValidateDns()
    {
        $m = new Model($this->pers);
        $m->addField('email', ['type' => ['email', 'dns_check' => true]]);

        $m->set('email', ' foo@gmail.com');

        $this->expectException(Model\Field\ValidationException::class);
        $this->expectExceptionMessage('Email domain does not exist');
        $m->set('email', ' foo@lrcanoetuhasnotdusantotehusontehuasntddaontehudnouhtd.com');
    }

    public function testEmailWithName()
    {
        $m = new Model($this->pers);
        $m->addField('email_name', ['type' => ['email', 'include_names' => true]]);
        $m->addField('email_names', ['type' => ['email', 'include_names' => true, 'allow_multiple' => true, 'dns_check' => true, 'separator' => [',', ';']]]);
        $m->addField('email_idn', ['type' => ['email', 'dns_check' => true]]);
        $m->addField('email', ['type' => 'email']);

        $m->set('email_name', 'Romans <me@gmail.com>');
        $m->set('email_names', 'Romans1 <me1@gmail.com>, Romans2 <me2@gmail.com>; Romans Žlutý Kůň <me3@gmail.com>');
        $m->set('email_idn', 'test@háčkyčárky.cz'); // official IDN test domain

        $this->expectException(Model\Field\ValidationException::class);
        $this->expectExceptionMessage('format is invalid');
        $m->set('email', 'Romans <me@gmail.com>');
    }
}
