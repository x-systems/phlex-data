<?php

declare(strict_types=1);

namespace Phlex\Data\Field;

use Phlex\Core\InitializerTrait;
use Phlex\Data\Model;

/**
 * Evaluate php expression after load.
 */
class Callback extends \Phlex\Data\Field
{
    use InitializerTrait {
        init as _init;
    }

    /**
     * Method to execute for evaluation.
     *
     * @var \Closure
     */
    public $expr;

    public $access = self::ACCESS_GET;

    public $persist = self::PERSIST_NONE;

    /**
     * Initialization.
     */
    protected function init(): void
    {
        $this->_init();

        $this->ui['table']['sortable'] = false;

        $this->onHookShortToOwner(Model::HOOK_AFTER_LOAD, function () {
            $model = $this->getOwner();

            $model->data[$this->short_name] = ($this->expr)($model);
        });
    }
}
