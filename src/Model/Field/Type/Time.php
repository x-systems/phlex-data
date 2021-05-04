<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

class Time extends DateTime
{
    protected function doNormalize($value)
    {
        $value = parent::doNormalize($value);

        return (clone $value)->setDate(1970, 1, 1);
    }

    public function toString($value): ?string
    {
        $value = $this->normalize($value);

        return $value ? $value->format('H:i:s' . ($value->format('u') > 0 ? '.u' : '')) : '';
    }
}
