<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Iterator;

class CallableIterator implements \Iterator
{
    private \Iterator $innerIterator;
    /**
     * @var \Closure
     */
    private $callback;

    public function __construct(\Iterator $innerIterator, \Closure $callback)
    {
        $this->innerIterator = $innerIterator;
        $this->callback = $callback;
    }

    public function current()
    {
        return \call_user_func($this->callback, $this->innerIterator->current(), $this->innerIterator->key());
    }

    public function next(): void
    {
        $this->innerIterator->next();
    }

    public function key()
    {
        return $this->innerIterator->key();
    }

    public function valid(): bool
    {
        return $this->innerIterator->valid();
    }

    public function rewind(): void
    {
        $this->innerIterator->rewind();
    }
}
