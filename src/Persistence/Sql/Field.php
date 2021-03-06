<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql;

use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;
use Phlex\Data\Persistence\Query;

/**
 * @property Persistence\Sql\Join $join
 */
class Field extends Model\Field implements Expressionable
{
    public function compare($value, $value2 = null): bool
    {
        if ($value instanceof Expressionable) {
            return false;
        }

        return parent::compare(...func_get_args());
    }

    /**
     * SQL fields are allowed to have expressions inside of them.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function normalize($value)
    {
        if ($value instanceof Expressionable) {
            return $value;
        }

        return parent::normalize($value);
    }

    public function getAlias(): ?string
    {
        return $this->getOption(Query::OPTION_FIELD_ALIAS);
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

        if ($model->getOption(Persistence\Sql\Query::OPTION_FIELD_PREFIX)) {
            $template = '{{}}.{}';
            $args = [
                $this->getTablePrefix(),
                $persistenceName,
            ];
        } else {
            // references set flag Persistence\Sql\Query::OPTION_FIELD_PREFIX, so no need to check them here
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
        return new Expression($template, $args);
    }

    protected function getTablePrefix(): string
    {
        return $this->hasJoin()
            ? ($this->getJoin()->foreign_alias ?: $this->getJoin()->elementId)
            : ($this->getOwner()->table_alias ?: $this->getOwner()->table);
    }
}
