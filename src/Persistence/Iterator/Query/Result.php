<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Iterator\Query;

class Result extends \Doctrine\DBAL\Result
{
    /** @var \Iterator|null */
    protected $iterator;

    /** @var \Closure|null */
    protected $generatorClosure;

    /** @var int|null */
    protected $rowsCount;

    public function __construct($iterator = null, int $rowsCount = null)
    {
        if ($iterator instanceof \Closure) {
            $this->generatorClosure = $iterator;

            $iterator = ($this->generatorClosure)();
        }

        if ($iterator instanceof \Iterator) {
            $iterator->rewind();
        }

        $this->iterator = $iterator;
        $this->rowsCount = $rowsCount;
    }

    public function fetchNumeric()
    {
        $row = $this->fetchAssociative();

        return $row ? array_values($row) : false;
    }

    public function fetchAssociative()
    {
        if (!$this->iterator || !$this->iterator->valid()) {
            return false;
        }

        $row = $this->iterator->current();

        $this->iterator->next();

        return $row;
    }

    public function fetchOne()
    {
        $row = $this->fetchAssociative();

        return $row ? reset($row) : false;
    }

    public function fetchAllNumeric(): array
    {
        $result = [];
        foreach ($this->fetchAllAssociative() as $key => $row) {
            $result[$key] = array_values($row);
        }

        return $result;
    }

    public function fetchAllAssociative(): array
    {
        $iterator = $this->getFreshIterator();

        return $iterator ? iterator_to_array($iterator, true) : [];
    }

    public function fetchFirstColumn(): array
    {
        $data = $this->fetchAllNumeric();

        return $data ? array_column($data, 0) : false;
    }

    public function iterateNumeric(): \Traversable
    {
        while (($row = $this->fetchNumeric()) !== false) {
            yield $row;
        }
    }

    public function iterateAssociative(): \Traversable
    {
        while (($row = $this->fetchAssociative()) !== false) {
            yield $row;
        }
    }

    public function iterateColumn(): \Traversable
    {
        while (($value = $this->fetchOne()) !== false) {
            yield $value;
        }
    }

    public function rowCount(): int
    {
        if ($this->rowsCount !== null) {
            return $this->rowsCount;
        }

        $iterator = $this->getFreshIterator();

        return $iterator ? iterator_count($iterator) : 0;
    }

    public function columnCount(): int
    {
        $firstRow = [];
        foreach ($this->getFreshIterator() as $row) {
            $firstRow = $row;

            break;
        }

        return count($firstRow);
    }

    public function free(): void
    {
    }

    protected function getFreshIterator(): ?\Traversable
    {
        if ($this->generatorClosure) {
            // execute the closure to create the generator
            return ($this->generatorClosure)();
        }

        return $this->iterator;
    }
}
