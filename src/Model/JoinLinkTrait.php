<?php

declare(strict_types=1);

namespace Phlex\Data\Model;

trait JoinLinkTrait
{
    /**
     * The short name of the join link.
     *
     * @var string|null
     */
    protected $joinName;

    public function getJoin(): Join
    {
        return $this->getOwner()->getElement($this->joinName);
    }

    public function hasJoin(): bool
    {
        return $this->joinName !== null;
    }

    public function setJoin(Join $join)
    {
        $this->joinName = $join->elementId;

        return $this;
    }
}
