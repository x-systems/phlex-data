<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence;

use Phlex\Data\Model;

class Session extends Array_
{
    protected $key;

    public function __construct(string $databaseName)
    {
        $this->key = $databaseName;

        parent::__construct($_SESSION[$this->key] ?? []);
    }

    public function configure(Model $model)
    {
        $model->onHookDynamic(
            Model::HOOK_AFTER_SAVE,
            static fn (Model $model) => $model->persistence,
            function (Model $model) {
                $_SESSION[$this->key][$model->table] = $this->data[$model->table];
            }
        );
    }
}
