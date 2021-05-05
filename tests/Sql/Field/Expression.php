<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\SQL\Field;

use Atk4\Dsql\Expression as SQLExpression;
use Atk4\Dsql\Expressionable;
use Phlex\Core\InitializerTrait;
use Phlex\Data\Model;

class Expression extends \Phlex\Data\Persistence\SQL\Field
{
    use InitializerTrait {
        init as _init;
    }

    /**
     * Used expression.
     *
     * @var \Closure|string|SQLExpression
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
    protected function init(): void
    {
        $this->_init();

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
    public function getDsqlExpression(SQLExpression $expression): SQLExpression
    {
        $expr = $this->expr;
        if ($expr instanceof \Closure) {
            $expr = $expr($this->getOwner(), $expression);
        }

        if ($expr instanceof Expressionable) {
            $expr = $expr->getDsqlExpression($expression);
        }

        if (is_string($expr)) {
            // If our Model has expr() method (inherited from Persistence\SQL) then use it
            if ($this->getOwner()->hasMethod('expr')) {
                return $this->getOwner()->expr('([])', [$this->getOwner()->expr($expr)]);
            }

            // Otherwise call it from expression itself
            return $expression->expr('([])', [$expression->expr($expr)]);
        }

        return $expr;
    }
}
