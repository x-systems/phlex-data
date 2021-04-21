<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql;

use Atk4\Dsql\Expression;
use Atk4\Dsql\Expressionable;
use Phlex\Data\Exception;

/**
 * @property Persistence\Sql\Join $join
 */
class Field extends \Phlex\Data\Model\Field implements Expressionable
{
    /**
     * SQL fields are allowed to have expressions inside of them.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function normalize($value)
    {
        if ($value instanceof Expression || $value instanceof Expressionable) {
            return $value;
        }

        return parent::normalize($value);
    }

    /**
     * When field is used as expression, this method will be called.
     * Universal way to convert ourselves to expression. Off-load implementation into persistence.
     */
    public function getDsqlExpression(Expression $expression): Expression
    {
        $model = $this->getOwner();

        if (!$model->persistence || !$model->persistence instanceof \Phlex\Data\Persistence\Sql) {
            throw (new Exception('Field must have SQL persistence if it is used as part of expression'))
                ->addMoreInfo('persistence', $model->persistence ?? null);
        }

        if (isset($model->persistence_data['use_table_prefixes'])) {
            $template = '{{}}.{}';
            $args = [
                $this->getTablePrefix(),
                $this->getPersistenceName(),
            ];
        } else {
            // references set flag use_table_prefixes, so no need to check them here
            $template = '{}';
            $args = [
                $this->getPersistenceName(),
            ];
        }

        // If our Model has expr() method (inherited from Persistence\Sql) then use it
        if ($model->hasMethod('expr')) {
            return $model->expr($template, $args);
        }

        // Otherwise call method from expression
        return $expression->expr($template, $args);
    }

    protected function getTablePrefix(): string
    {
        return $this->hasJoin()
            ? ($this->getJoin()->foreign_alias ?: $this->getJoin()->short_name)
            : ($this->getOwner()->table_alias ?: $this->getOwner()->table);
    }
}
