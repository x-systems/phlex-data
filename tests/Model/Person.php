<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Model;

use Phlex\Data\Model;

class Person extends Model
{
    public $table = 'person';

    protected function init(): void
    {
        parent::init();
        $this->addField('name');
        $this->addField('surname');
        $this->addField('gender', ['enum' => ['M', 'F']]);
    }
}
