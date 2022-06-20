<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Field\Reference;

use Phlex\Data\Model;
use Phlex\Data\Persistence;

class HasMany extends Model\Field\Reference\HasMany
{
    public function getTheirEntity(array $defaults = []): Model
    {
        return parent::getTheirEntity($defaults)->setOption(Persistence\Sql\Query::OPTION_FIELD_PREFIX);
    }
}
