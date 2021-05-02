<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field;

use Phlex\Core\DiContainerTrait;
use Phlex\Core\Factory;
use Phlex\Data\Model;

class Type
{
    use DiContainerTrait;

    protected static $registry = [
        'default' => [Type\Generic::class],
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
        'array' => [Type\Array_::class],
        'object' => [Type\Object_::class],
    ];

    /**
     * Registry of codecs to be used.
     *
     * Persistence class => Codec seed
     *
     * @var array<string, array>
     */
    public $codecs = [];

    /**
     * Defaults to be set to the codec.
     *
     * @var array<string, mixed>
     */
    public $codec = [];

    /**
     * Resolve field type to seed from Field::$registry.
     *
     * @param string|array|object $type
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
        if (is_array($type) && isset(self::$registry[$type[0] ?? null])) {
            return self::$registry[$type[0]] + $type;
        }

        return self::$registry[$type ?? 'default'];
    }

    public function createCodec(Model\Field $field)
    {
        $persistence = $field->getOwner()->persistence;

        $persistenceClass = get_class($persistence);

        if (!isset($this->codecs[$persistenceClass])) {
            // resolve codec declared with the Model\Field\Type::$codecs
            if (!$codecSeed = self::resolveCodecFromRegistry($persistenceClass, (array) $this->codecs)) {
                // resolve codec declared with the Persistence
                $codecSeed = self::resolveCodecFromRegistry(static::class, $persistence->getCodecs());
            }

            if (!is_object($codecSeed)) {
                $codecSeed = Factory::factory(Factory::mergeSeeds((array) $this->codec, $codecSeed), [$field]);
            }

            // cache resolved codec
            $this->codecs[$persistenceClass] = $codecSeed;
        }

        return Factory::factory($this->codecs[$persistenceClass], (array) $this->codec);
    }

    protected static function resolveCodecFromRegistry(string $searchClass, array $registry)
    {
        foreach (array_merge([$searchClass], class_parents($searchClass)) as $class) {
            if ($codecClass = $registry[$class] ?? null) {
                break;
            }
        }

        return $codecClass ?? $registry[0] ?? null;
    }

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
     * Validate and normalize value.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function normalize($value)
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
        return (string) $this->normalize($value);
    }

    protected function assertScalar($value): void
    {
        if ($value !== null && !is_scalar($value)) {
            throw new Type\ValidationException('Must use scalar value');
        }
    }
}
