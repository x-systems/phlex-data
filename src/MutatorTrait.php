<?php

declare(strict_types=1);

namespace Phlex\Data;

trait MutatorTrait
{
    /**
     * Stores object custom codec resolution array.
     *
     * @var array
     */
    protected $codecs = [];

    /**
     * Retrieve the default codecs for the persistence class.
     */
    public static function getDefaultCodecs(): array
    {
        $parentClass = get_parent_class(static::class);

        return (static::$defaultCodecs ?? []) + ($parentClass ? $parentClass::getDefaultCodecs() : []);
    }

    /**
     * Retrieve the active codecs for the persistence object.
     */
    public function getCodecs(): array
    {
        return (array) $this->codecs + $this->getDefaultCodecs();
    }

    /**
     * Add custom codecs to Persistence.
     *
     * @return static
     */
    public function setCodecs(array $codecs)
    {
        $this->codecs = $codecs;

        return $this;
    }
}
