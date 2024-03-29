<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Codec;

use Doctrine\DBAL\Types\Types;
use Phlex\Data\Persistence\Sql;

class Selectable extends Sql\Codec
{
    protected $separator = '++';

    protected function doEncode($value)
    {
        if ($value === '') {
            return '';
        }

        if (!$this->storesMultipleValues()) {
            return $value;
        }

        if (is_string($value) && substr($value, 0, 2) === $this->separator && substr($value, -2) === $this->separator) {
            return $value;
        }

        return $this->separator . implode($this->separator, (array) $value) . $this->separator;
    }

    public function decode($value)
    {
        if (!$this->storesMultipleValues()) {
            return parent::decode($value);
        }

        if ($value === null || $value === '') {
            return [];
        }

        return $this->doDecode($value);
    }

    protected function doDecode($value)
    {
        if ($this->storesMultipleValues()) {
            if (is_array($value)) {
                return $value;
            }

            return array_values(array_filter(explode($this->separator, $value)));
        }

        return $value;
    }

    public function getQueryArguments($operator, $value): array
    {
        if ($this->storesMultipleValues()) {
            $expr = Sql\Expression::or();

            if ($value instanceof Sql\Expressionable) {
                $value = new Sql\Expression\Concat('%' . $this->separator, $value, $this->separator . '%');

                $expr->where($this->field, 'like', $value);
            } else {
                foreach ((array) $value as $v) {
                    $expr->where($this->field, 'like', '%' . $this->doEncode($v) . '%');
                }
            }

            return [$expr, null, null];
        }

        return parent::getQueryArguments($operator, $value);
    }

    public function getColumnTypeName(): string
    {
        return $this->storesMultipleValues() ? Types::TEXT : Types::STRING;
    }

    protected function storesMultipleValues(): bool
    {
        return $this->getValueType()->allowMultipleSelection; // @phpstan-ignore-line
    }
}
