<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Scope;

use Phlex\Core\InitializerTrait;
use Phlex\Core\TrackableTrait;
use Phlex\Data\Exception;
use Phlex\Data\Model;

/**
 * @method Model\Scope getOwner()
 */
abstract class AbstractScope
{
    use InitializerTrait;
    use TrackableTrait;

    /**
     * Method is executed when the scope is added to parent scope using Scope::add().
     */
    protected function doInitialize(): void
    {
        if (!$this->getOwner() instanceof self) {
            throw new Exception('Scope can only be added as element to scope');
        }

        $this->onChangeModel();
    }

    abstract protected function onChangeModel(): void;

    /**
     * Get the model this condition is associated with.
     */
    public function getModel(): ?Model
    {
        return $this->issetOwner() ? $this->getOwner()->getModel() : null;
    }

    /**
     * Empty the scope object.
     *
     * @return static
     */
    abstract public function clear();

    /**
     * Negate the scope object
     * e.g from 'is' to 'is not'.
     *
     * @return static
     */
    abstract public function negate();

    /**
     * Return if scope has any conditions.
     */
    abstract public function isEmpty(): bool;

    /**
     * Convert the scope to human readable words when applied on $model.
     */
    abstract public function toWords(Model $model = null): string;

    /**
     * Simplifies by peeling off nested group conditions with single contained component.
     * Useful for converting (((field = value))) to field = value.
     */
    public function simplify(): self
    {
        return $this;
    }

    /**
     * Returns if scope contains several conditions.
     */
    public function isCompound(): bool
    {
        return false;
    }
}
