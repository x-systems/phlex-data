<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field;

use Phlex\Core\InitializerTrait;
use Phlex\Data\Model;

/**
 * Evaluate php expression after load.
 */
class Callback extends Model\Field
{
    use InitializerTrait;

    /**
     * Method to execute for evaluation.
     *
     * @var \Closure
     */
    public $expr;

    public $access = self::ACCESS_GET;

    public $persist = self::PERSIST_NONE;

    protected function doInitialize(): void
    {
        $this->ui['table']['sortable'] = false;

        $this->onHookShortToOwner(Model::HOOK_AFTER_LOAD, function () {
            $this->getOwner()->getEntity()->set($this->getKey(), ($this->expr)($this->getOwner()));
        });
    }
}
