<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Scope;

class Placeholder
{
    /** @var string */
    protected $caption;

    protected $value;

    public function __construct($caption, $value)
    {
        $this->caption = $caption;
        $this->value = $value;
    }

    public function getCaption(Condition $condition)
    {
        if (is_callable($this->caption)) {
            return ($this->caption)($condition);
        }

        return $this->caption;
    }

    public function getValue(Condition $condition)
    {
        if (is_callable($this->value)) {
            return ($this->value)($condition);
        }

        return $this->value;
    }

    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }
}
