<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field;

use Phlex\Core\Factory;
use Phlex\Core\InjectableTrait;

class Serializer
{
    use InjectableTrait;

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

    public static function resolve($preset)
    {
        $serializerSeed = $preset;
        if (is_string($preset)) {
            $serializerSeed = self::$presets[$preset] ?? [];
        }

        return Factory::factory(Factory::mergeSeeds([self::class], $serializerSeed));
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
