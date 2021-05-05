<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field;

use Phlex\Core\DiContainerTrait;
use Phlex\Core\Factory;
use Phlex\Data\Persistence\Codec;

class Serializer
{
    use DiContainerTrait;
    use TypeTrait;

    protected static $presets = [
        'serialize' => ['encodeFx' => 'serialize', 'decodeFx' => 'unserialize'],
        'json' => ['encodeFx' => [Codec::class, 'jsonEncode'], 'decodeFx' => [Codec::class, 'jsonDecode']],
        'base64' => ['encodeFx' => 'base64_encode', 'decodeFx' => 'base64_encode'],
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

    public function encode($value)
    {
        return $this->encodeFx ? ($this->encodeFx)($value) : $value;
    }

    public function decode($value)
    {
        return $this->decodeFx ? ($this->decodeFx)($value) : $value;
    }
}
