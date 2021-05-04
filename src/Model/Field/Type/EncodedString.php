<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

class EncodedString extends String_
{
    protected function doNormalize($value)
    {
        if ($value === null) {
            return;
        }

        // remove all line-ends
        return trim(str_replace(["\r", "\n"], '', parent::doNormalize($value)));
    }
}
