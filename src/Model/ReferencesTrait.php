<?php

declare(strict_types=1);

namespace Phlex\Data\Model;

use Phlex\Data\Exception;

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
    protected function doAddReference(array $seed, string $link, array $defaults = []): Reference
    {
        $defaults[0] = $link;

        $reference = Reference::fromSeed($seed, $defaults);

        // if reference with such name already exists, then throw exception
        if ($this->hasElement($name = $reference->getDesiredName())) {
            throw (new Exception('Reference with such name already exists'))
                ->addMoreInfo('name', $name)
                ->addMoreInfo('link', $link)
                ->addMoreInfo('defaults', $defaults);
        }

        return $this->add($reference);
    }

    /**
     * Add generic relation. Provide your own call-back that will return the model.
     */
    public function addReference(string $link, array $defaults): Reference
    {
        return $this->doAddReference($this->_default_seed_addReference, $link, $defaults);
    }

    /**
     * Add hasOne reference.
     *
     * @return Reference\HasOne
     */
    public function hasOne(string $link, array $defaults = []) // : Reference
    {
        return $this->doAddReference($this->_default_seed_hasOne, $link, $defaults); // @phpstan-ignore-line
    }

    /**
     * Add hasMany reference.
     *
     * @return Reference\HasMany
     */
    public function hasMany(string $link, array $defaults = []) // : Reference
    {
        return $this->doAddReference($this->_default_seed_hasMany, $link, $defaults); // @phpstan-ignore-line
    }

    /**
     * Add containsOne reference.
     *
     * @return Reference\ContainsOne
     */
    public function containsOne(string $link, array $defaults = []) // : Reference
    {
        return $this->doAddReference($this->_default_seed_containsOne, $link, $defaults); // @phpstan-ignore-line
    }

    /**
     * Add containsMany reference.
     *
     * @return Reference\ContainsMany
     */
    public function containsMany(string $link, array $defaults = []) // : Reference
    {
        return $this->doAddReference($this->_default_seed_containsMany, $link, $defaults); // @phpstan-ignore-line
    }

    /**
     * Traverse to related model.
     *
     * @return \Phlex\Data\Model
     */
    public function ref(string $link, array $defaults = []): self
    {
        return $this->getReference($link)->ref($defaults);
    }

    /**
     * Return related model.
     *
     * @return \Phlex\Data\Model
     */
    public function refModel(string $link, array $defaults = []): self
    {
        return $this->getReference($link)->createTheirModel($defaults);
    }

    /**
     * Returns model that can be used for generating sub-query actions.
     *
     * @return \Phlex\Data\Model
     */
    public function refLink(string $link, array $defaults = []): self
    {
        return $this->getReference($link)->refLink($defaults);
    }

    /**
     * Returns the reference.
     */
    public function getReference(string $link): Reference
    {
        return $this->getElement('#ref_' . $link);
    }

    /**
     * Returns all references.
     */
    public function getReferences(): array
    {
        $refs = [];
        foreach ($this->elements as $key => $val) {
            if (substr($key, 0, 5) === '#ref_') {
                $refs[substr($key, 5)] = $val;
            }
        }

        return $refs;
    }

    /**
     * Returns true if reference exists.
     */
    public function hasReference(string $link): bool
    {
        return $this->hasElement('#ref_' . $link);
    }
}
