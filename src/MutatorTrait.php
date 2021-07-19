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

        return (static::$defaultCodecs ?? []) + ($parentClass && method_exists($parentClass, 'getDefaultCodecs') ? $parentClass::getDefaultCodecs() : []);
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

    public function getCodec(Model\Field $field)
    {
        return $field->getCodec($this);
    }

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
    public function encodeRow(Model $model, array $row): array
    {
        $result = [];
        foreach ($row as $key => $value) {
            // We have no knowledge of the field, it wasn't defined, so
            // we will leave it as-is.
            if (!$model->hasField($key)) {
                $result[$key] = $value;

                continue;
            }

            $codec = $model->getField($key)->getCodec($this);

            $result[$codec->getKey()] = $codec->encode($value);
        }

        return $result;
    }

    /**
     * Will convert one row of data from Mutator-specific
     * types to PHP native types.
     *
     * NOTE: Please DO NOT perform "actual" field mapping here, because data
     * may be "aliased" from SQL persistences or mapped depending on persistence
     * driver.
     */
    public function decodeRow(Model $model, array $row): array
    {
        $result = [];
        foreach ($model->getFields() as $field) {
            $codec = $field->getCodec($this);
            $key = $codec->getKey();

            if (array_key_exists($key, $row)) {
                $result[$field->short_name] = $codec->decode($row[$key]);
            }
        }

        return $result;
    }
}
