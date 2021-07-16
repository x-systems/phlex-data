<?php

declare(strict_types=1);

namespace Phlex\Data;

interface MutatorInterface
{
    /**
     * Retrieve the active codecs for the data mutator object.
     */
    public function getCodecs();

    /**
     * Add custom codecs to data mutator.
     *
     * @return static
     */
    public function setCodecs(array $codecs);

    /**
     * Will convert one row of data from native PHP types into
     * mutator defined types. This will also take care of the "actual"
     * field keys using the Codec::getKey() method. Example:.
     *
     * In:
     *  [
     *    'name'=>' John Smith',
     *    'age'=>30,
     *    'password'=>'abc',
     *    'is_married'=>true,
     *  ]
     *
     *  Out:
     *   [
     *     'first_name'=>'John Smith',
     *     'age'=>30,
     *     'is_married'=>1
     *   ]
     */
    public function encodeRow(Model $model, array $row): array;

    /**
     * Will convert one row of data from Mutator-specific
     * types to PHP native types.
     *
     * NOTE: Please DO NOT perform "actual" field mapping here, because data
     * may be "aliased" from SQL persistences or mapped depending on persistence
     * driver.
     */
    public function decodeRow(Model $model, array $row): array;
}
