<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql;

use Atk4\Dsql\Expression;
use Atk4\Dsql\Expressionable;

/**
 * @property Persistence\Sql\Join $join
 */
class Field extends \Phlex\Data\Model\Field implements Expressionable
{
    use Field\ExpressionableTrait;

    /**
     * SQL fields are allowed to have expressions inside of them.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function normalize($value)
    {
        if ($value instanceof Expression
            || $value instanceof Expressionable) {
            return $value;
        }

        return parent::normalize($value);
    }
}
