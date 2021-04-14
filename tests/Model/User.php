<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Model;

use Phlex\Data\Model;

class User extends Model
{
    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('surname');
    }
}
