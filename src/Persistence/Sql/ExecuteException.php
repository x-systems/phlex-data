<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql;

class ExecuteException extends \Phlex\Data\Exception
{
    public function getErrorMessage(): string
    {
        return $this->getParams()['error'];
    }

    public function getDebugQuery(): string
    {
        return $this->getParams()['query'];
    }
}
