<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field;

use Phlex\Core;
use Phlex\Data\MutatorInterface;

class Serializer
{
    use Core\InjectableTrait;

    protected static $presets = [
        'serialize' => ['encodeFx' => 'serialize', 'decodeFx' => 'unserialize'],
        'json' => ['encodeFx' => [Codec::class, 'jsonEncode'], 'decodeFx' => [Codec::class, 'jsonDecode']],
        'base64' => ['encodeFx' => 'base64_encode', 'decodeFx' => 'base64_decode'],
        'md5' => ['encodeFx' => 'md5'],
    ];

    /** @var \Closure|null */
    protected $encodeFx;

    /** @var \Closure|null */
    protected $decodeFx;

    public static function resolve($presets, MutatorInterface $mutator = null)
    {
        $serializerSeed = Core\Utils::resolveFromRegistry($presets, $mutator ? get_class($mutator) : '');
        if (is_string($serializerSeed)) {
            $serializerSeed = self::$presets[$serializerSeed] ?? [];
        }

        return Core\Factory::factory(Core\Factory::mergeSeeds([self::class], $serializerSeed));
    }

    public function encode($value): string
    {
        return $this->encodeFx ? ($this->encodeFx)($value) : $value;
    }

    public function decode($value)
    {
        return $this->decodeFx ? ($this->decodeFx)($value) : $value;
    }
}
