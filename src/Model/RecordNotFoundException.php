<?php

declare(strict_types=1);

namespace Phlex\Data\Model;

use Phlex\Data\Exception;
use Phlex\Data\Model;

class RecordNotFoundException extends Exception
{
    public function __construct(Model $model, $id = null)
    {
        $this
            ->addMoreInfo('model', $model)
            ->addMoreInfo('scope', $model->scope()->toWords());

        if ($id !== null) {
            $this->addMoreInfo('id', $id);
        }

        parent::__construct('Record not found', 404);
    }
}
