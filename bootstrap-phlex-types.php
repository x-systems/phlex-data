<?php

declare(strict_types=1);

namespace Phlex\Data;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

// TODO for types to DBAL migration, might be removed later

final class PhlexTypes
{
    public const MONEY = 'money';
    public const PASSWORD = 'password';
}

class PhlexTypeMoney extends Type
{
    public function getName(): string
    {
        return PhlexTypes::MONEY;
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return Type::getType(Types::FLOAT)->getSQLDeclaration($fieldDeclaration, $platform);
    }
}

class PhlexTypePassword extends Type
{
    public function getName(): string
    {
        return PhlexTypes::PASSWORD;
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return Type::getType(Types::STRING)->getSQLDeclaration($fieldDeclaration, $platform);
    }
}

Type::addType(PhlexTypes::MONEY, PhlexTypeMoney::class);
Type::addType(PhlexTypes::PASSWORD, PhlexTypePassword::class);
