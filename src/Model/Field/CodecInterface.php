<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field;

use Phlex\Data\Model;

interface CodecInterface
{
    public function encode($value);

    public function decode($value);

    public function getField(): Model\Field;

    public function getValueType(): Model\Field\Type;
}
