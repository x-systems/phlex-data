<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql;

use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

/**
 * @property Persistence\Sql\Join $join
 */
class Field extends Model\Field implements Expressionable
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
    public function toSqlExpression(): Expression
    {
        $model = $this->getOwner();

        if (!$model->persistence || !$model->persistence instanceof Persistence\Sql) {
            throw (new Exception('Field must have SQL persistence if it is used as part of expression'))
                ->addMoreInfo('persistence', $model->persistence ?? null);
        }

        $persistenceName = $this->getCodec($model->persistence)->getKey();

        if ($model->getOption(Persistence\Sql::OPTION_USE_TABLE_PREFIX)) {
            $template = '{{}}.{}';
            $args = [
                $this->getTablePrefix(),
                $persistenceName,
            ];
        } else {
            // references set flag OPTION_USE_TABLE_PREFIX, so no need to check them here
            $template = '{}';
            $args = [
                $persistenceName,
            ];
        }

        // If our Model has expr() method (inherited from Persistence\Sql) then use it
        if ($model->hasMethod('expr')) {
            return $model->expr($template, $args);
        }

        // Otherwise call method from expression
        return $model->persistence->expr($template, $args);
    }

    protected function getTablePrefix(): string
    {
        return $this->hasJoin()
            ? ($this->getJoin()->foreign_alias ?: $this->getJoin()->short_name)
            : ($this->getOwner()->table_alias ?: $this->getOwner()->table);
    }
}
