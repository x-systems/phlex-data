<?php

declare(strict_types=1);

namespace Phlex\Data\Model;

/**
 * Provides native Model methods for manipulating related component options.
 */
trait OptionsTrait
{
    /**
     * Holds various options for related components - Persistence, Controllers, sub-components, etc.
     *
     * As best practice the option keys can be defined as constants in the related component
     * e.g. Persistence::OPTION_USE_TABLE_PREFIX = self::class . '@use_table_prefix'
     *
     * @var array
     */
    protected $options = [];

    /**
     * Retrieves an option from the array.
     *
     * @return mixed
     */
    public function getOption(string $key)
    {
        return $this->option[$key] ?? null;
    }

    /**
     * Sets the option in the array.
     *
     * Default use is as a boolean flag
     *
     * @param mixed $value
     */
    public function setOption(string $key, $value = true) //:static
    {
        $this->option[$key] = $value;

        return $this;
    }

    /**
     * Unsets the option in the array.
     *
     * @param mixed $value
     */
    public function unsetOption(string $key) //:static
    {
        unset($this->option[$key]);

        return $this;
    }
}
