<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Field;

use Phlex\Core\InitializerTrait;
use Phlex\Data\Model;
use Phlex\Data\Persistence\Sql;

class Expression extends Sql\Field
{
    use InitializerTrait;

    /**
     * Used expression.
     *
     * @var \Closure|string|Sql\Expression
     */
    public $expr;

    // Expressions can only load from persistence.
    public $persist = self::PERSIST_LOAD;

    // Expressions are always read_only.
    public $access = self::ACCESS_GET;

    /**
     * Specifies how to aggregate this.
     */
    public $aggregate;

    /**
     * Aggregation by concatenation.
     */
    public $concat;

    /**
     * When defining as aggregate, this will point to relation object.
     */
    public $aggregate_relation;

    /**
     * Specifies which field to use.
     */
    public $field;

    /**
     * Initialization.
     */
    protected function doInitialize(): void
    {
        if ($this->getOwner()->reload_after_save === null) {
            $this->getOwner()->reload_after_save = true;
        }

        if ($this->concat) {
            $this->onHookShortToOwner(Model::HOOK_AFTER_SAVE, \Closure::fromCallable([$this, 'afterSave']));
        }
    }

    /**
     * Possibly that user will attempt to insert values here. If that is the case, then
     * we would need to inject it into related hasMany relationship.
     */
    public function afterSave()
    {
    }

    /**
     * Should this field use alias?
     * Expression fields always need alias.
     */
    public function useAlias(): bool
    {
        return true;
    }

    /**
     * When field is used as expression, this method will be called.
     */
    public function toExpression(): Sql\Expression
    {
        $expr = $this->expr;
        if ($expr instanceof \Closure) {
            $expr = $expr($this->getOwner());
        }

        if (is_string($expr)) {
            // If our Model has expr() method (inherited from Persistence\Sql) then use it
            if ($this->getOwner()->hasMethod('expr')) {
                $expr = $this->getOwner()->expr($expr);
            }

            // Otherwise call it from expression itself
            return new Sql\Expression('([])', [$expr]);
        }

        return $expr->toExpression();
    }
}
