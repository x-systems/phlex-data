<?php

declare(strict_types=1);

namespace Phlex\Data\Model;

use Phlex\Data\Persistence;

/**
 * Provides methods common for elements having Model as Owner.
 */
trait ElementTrait
{
    public function getPersistence(): ?Persistence
    {
        return $this->issetOwner() ? $this->getOwner()->persistence : null;
    }
}
