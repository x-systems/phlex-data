<?php

declare(strict_types=1);

namespace Phlex\Data\Model;

class Entry
{
    private $data = [];

    private $dirty = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function setMulti(array $values)
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function set(string $key, $value)
    {
        $this->dirty[$key] = $value;

        return $this;
    }

    public function reset(string $key, $default = null)
    {
        unset($this->dirty[$key]);

        if ($default !== null && !$this->isLoaded($key)) {
            $this->data[$key] = $default;
        }

        return $this;
    }

    public function unset(string $key)
    {
        unset($this->data[$key], $this->dirty[$key]);

        return $this;
    }

    public function get(string $key, $default = null)
    {
        if ($this->isDirty($key)) {
            return $this->getDirty($key);
        }

        return $this->getLoaded($key, $default);
    }

    public function isset(string $key): bool
    {
        return $this->isDirty($key) || $this->isLoaded($key);
    }

    public function isLoaded(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function isDirty(string $key): bool
    {
        return array_key_exists($key, $this->dirty);
    }

    public function getLoaded(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->data;
        }

        return $this->data[$key] ?? $default;
    }

    public function getDirty(string $key = null)
    {
        if ($key === null) {
            return $this->dirty;
        }

        return $this->dirty[$key] ?? null;
    }

    public function getAll(): array
    {
        return array_merge($this->data, $this->dirty);
    }

    public function commit(array $data = [])
    {
        $this->data = $data ?: $this->getAll();

        $this->dirty = [];

        return $this;
    }
}
