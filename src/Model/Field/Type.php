<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field;

use Phlex\Core\DiContainerTrait;
use Phlex\Core\Factory;
use Phlex\Data;

abstract class Type
{
    use DiContainerTrait;

    protected static $registry = [
        [Type\Generic::class],
        'generic' => [Type\Generic::class],
        'bool' => [Type\Boolean::class],
        'boolean' => [Type\Boolean::class],
        'float' => [Type\Float_::class],
        'integer' => [Type\Integer::class],
        'int' => [Type\Integer::class],
        'money' => [Type\Money::class],
        'text' => [Type\Text::class],
        'string' => [Type\String_::class],
        'password' => [Type\Password::class],
        'email' => [Type\Email::class],
        'datetime' => [Type\DateTime::class],
        'date' => [Type\Date::class],
        'time' => [Type\Time::class],
        'enum' => [Type\Selectable::class, 'allowMultipleSelection' => false],
        'list' => [Type\Selectable::class, 'allowMultipleSelection' => true],
        'array' => [Type\Array_::class],
        'object' => [Type\Object_::class],
    ];

    /**
     * Registry of codecs to be used.
     *
     * Persistence class => Codec seed
     *
     * @var array<string, array|\Phlex\Data\Persistence\Codec>
     */
    public $codecs = [];

    /**
     * Defaults to be set to the codec.
     *
     * @var array<string, mixed>
     */
    public $codec = [];

    /**
     * Register custom field type to be resolved.
     *
     * @param string|array      $type
     * @param string|array|null $seed
     */
    public static function register($type, $seed = null)
    {
        if (is_array($types = $type)) {
            foreach ($types as $type => $seed) {
                self::register($type, $seed);
            }
        }

        self::$registry[$type] = $seed;
    }

    /**
     * Resolve field type to seed from Field::$registry.
     *
     * @param string|array|object|null $type
     *
     * @return array|object
     */
    public static function resolve($type)
    {
        if (is_object($type)) {
            return $type;
        }

        // using seed with alias e.g. ['string', 'maxLength' => 50]
        // convert the alias to actual class name and proper seed array
        if (is_array($type)) {
            return self::$registry[$type[0] ?? 0] + $type;
        }

        return self::$registry[$type ?? 0];
    }

    public function createCodec(Data\Model\Field $field, Data\MutatorInterface $mutator = null)
    {
        $mutator = $mutator ?? $field->getPersistence();

        $mutatorClass = get_class($mutator);

        $codecSeed = $this->codecs[$mutatorClass] ?? null;

        if (!$codecSeed/*  || (is_object($codecSeed) && $codecSeed->getField() !== $field) */) {
            // resolve codec declared with the Data\Model\Field\Type::$codecs
            $codecSeedFieldType = self::resolveFromRegistry((array) $this->codecs, $mutatorClass);

            // resolve codec declared with the Mutator
            $codecSeedMutator = static::resolveFromRegistry($mutator->getCodecs());

            // cache resolved codec
            $codecSeed = $this->codecs[$mutatorClass] = Factory::factory(Factory::mergeSeeds((array) $this->codec, $codecSeedFieldType, $codecSeedMutator), [$mutator, $field]);
        }

        return Factory::factory($codecSeed, (array) $this->codec);
    }

    public static function resolveFromRegistry(array $registry, string $searchClass = null)
    {
        $searchClass = $searchClass ?? static::class;

        if (array_key_exists($searchClass, $registry)) {
            return $registry[$searchClass];
        }

        foreach ($registry as $mapClass => $seed) {
            if (is_string($mapClass) && is_a($searchClass, $mapClass, true)) {
                return $seed;
            }
        }

        return $registry[0] ?? null;
    }

    public function normalize($value)
    {
        if ($value === null || $value === '') {
            return;
        }

        return $this->doNormalize($value);
    }

    /**
     * Validate and normalize value.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    protected function doNormalize($value)
    {
        return $value;
    }

    /**
     * Compare two values.
     *
     * @param mixed $value1
     * @param mixed $value2
     */
    public function compare($value1, $value2): bool
    {
        return $this->normalize($value1) === $this->normalize($value2);
    }

    protected function compareAsString($value1, $value2): bool
    {
        return $this->toString($value1) === $this->toString($value2);
    }

    /**
     * Cast field value to string.
     *
     * @param mixed $value
     */
    public function toString($value): ?string
    {
        return (string) $this->doNormalize($value);
    }

    protected function assertScalar($value): void
    {
        if ($value !== null && !is_scalar($value)) {
            throw new Type\ValidationException('Must use scalar value');
        }
    }

    /**
     * Method is called when using 'serialize' in the Field type seed.
     */
    public function setSerialize($serializerPresets)
    {
        foreach ((array) $serializerPresets as $mutatorClass => $serializerPreset) {
            $this->codecs[$mutatorClass] = array_merge($this->codecs[$mutatorClass] ?? [], ['serialize' => $serializerPreset]);
        }

        return $this;
    }
}
