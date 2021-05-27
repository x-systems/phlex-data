<?php

declare(strict_types=1);

namespace Phlex\Data\Model;

use Phlex\Core\HookTrait;

/**
 * Provides native Model methods for manipulating related component options.
 */
trait OptionsTrait
{
    use HookTrait;

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
    public function getOption(string $key, $default = null)
    {
        return array_key_exists($key, $this->options) ? $this->options[$key] : $default;
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
        $this->options[$key] = $value;

        if (defined('self::HOOK_SET_OPTION')) {
            $this->hook(self::HOOK_SET_OPTION, [$key]);
        }

        return $this;
    }

    /**
     * Unsets the option in the array.
     *
     * @param mixed $value
     */
    public function unsetOption(string $key) //:static
    {
        unset($this->options[$key]);

        if (defined('self::HOOK_SET_OPTION')) {
            $this->hook(self::HOOK_SET_OPTION, [$key]);
        }

        return $this;
    }

    public function setOptions(array $options) //:static
    {
        foreach ($options as $key => $value) {
            $this->setOption($key, $value);
        }

        return $this;
    }
}
