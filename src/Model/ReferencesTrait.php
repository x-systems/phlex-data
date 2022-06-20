<?php

declare(strict_types=1);

namespace Phlex\Data\Model;

use Phlex\Data\Model\Field\Reference;

/**
 * Provides native Model methods for manipulating model references.
 */
trait ReferencesTrait
{
    /**
     * The seed used by addReference() method.
     *
     * @var array
     */
    public $_default_seed_addReference = [Reference::class];

    /**
     * The seed used by hasOne() method.
     *
     * @var array
     */
    public $_default_seed_hasOne = [Reference\HasOne::class];

    /**
     * The seed used by hasMany() method.
     *
     * @var array
     */
    public $_default_seed_hasMany = [Reference\HasMany::class];

    /**
     * The seed used by withMany() method.
     *
     * @var array
     */
    public $_default_seed_withMany = [Reference\WithMany::class];

    /**
     * The seed used by containsOne() method.
     *
     * @var array
     */
    public $_default_seed_containsOne = [Reference\ContainsOne::class];

    /**
     * The seed used by containsMany() method.
     *
     * @var array
     */
    public $_default_seed_containsMany = [Reference\ContainsMany::class];

    /**
     * @param array<string, mixed> $defaults Properties which we will pass to Reference object constructor
     */
    protected function doAddReference(array $seed, string $key, array $defaults = []): Reference
    {
        return $this->addField($key, Field\Reference::fromSeed($seed, $defaults));
    }

    /**
     * Add generic relation. Provide your own call-back that will return the model.
     */
    public function addReference(string $key, array $defaults): Reference
    {
        return $this->doAddReference($this->_default_seed_addReference, $key, $defaults);
    }

    /**
     * Add hasOne reference.
     *
     * @return Reference\HasOne
     */
    public function hasOne(string $key, array $defaults = []) // : Reference
    {
        return $this->doAddReference($this->_default_seed_hasOne, $key, $defaults); // @phpstan-ignore-line
    }

    /**
     * Add hasMany reference.
     *
     * @return Reference\HasMany
     */
    public function hasMany(string $key, array $defaults = []) // : Reference
    {
        return $this->doAddReference($this->_default_seed_hasMany, $key, $defaults); // @phpstan-ignore-line
    }

    /**
     * Add withMany reference.
     *
     * @return Reference\WithMany
     */
    public function withMany(string $key, array $defaults = []) // : Reference
    {
        return $this->doAddReference($this->_default_seed_withMany, $key, $defaults); // @phpstan-ignore-line
    }

    /**
     * Add containsOne reference.
     *
     * @return Reference\ContainsOne
     */
    public function containsOne(string $key, array $defaults = []) // : Reference
    {
        return $this->doAddReference($this->_default_seed_containsOne, $key, $defaults); // @phpstan-ignore-line
    }

    /**
     * Add containsMany reference.
     *
     * @return Reference\ContainsMany
     */
    public function containsMany(string $key, array $defaults = []) // : Reference
    {
        return $this->doAddReference($this->_default_seed_containsMany, $key, $defaults); // @phpstan-ignore-line
    }

    /**
     * Traverse to related model.
     *
     * @return \Phlex\Data\Model
     */
    public function ref(string $key, array $defaults = []): self
    {
        return $this->getReference($key)->getTheirEntity($defaults);
    }

    /**
     * Returns model that can be used for generating sub-query actions.
     *
     * @return \Phlex\Data\Model
     */
    public function refLink(string $key, array $defaults = []): self
    {
        return $this->getReference($key)->refLink($defaults);
    }

    /**
     * Returns the reference.
     */
    public function getReference(string $key): Reference
    {
        return $this->getField($key);
    }

    /**
     * Returns all references.
     */
    public function getReferences(): array
    {
        return array_filter($this->getFields(), fn ($field) => $field instanceof Field\Reference);
    }

    /**
     * Returns true if reference exists.
     */
    public function hasReference(string $key): bool
    {
        return $this->hasField($key) && ($this->getField($key) instanceof Reference);
    }
}
