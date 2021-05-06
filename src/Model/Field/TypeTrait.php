<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field;

use Phlex\Core\Factory;

trait TypeTrait
{
    /**
     * @var string|Type
     */
    public $type;

    public function getValueType(): Type
    {
        if (!is_object($this->type)) {
            $this->type = Factory::factory(Type::resolve($this->type));
        }

        return $this->type;
    }
}
