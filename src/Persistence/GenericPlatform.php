<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence;

use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Platforms;

class GenericPlatform extends Platforms\AbstractPlatform
{
    private function createNotSupportedException(): \Exception
    {
        if (\Atk4\Dsql\Connection::isComposerDbal2x()) {
            // @phpstan-ignore-next-line
            return \Doctrine\DBAL\DBALException::notSupported('SQL');
        }

        return DbalException::notSupported('SQL');
    }

    public function getName(): string
    {
        return 'phlex_data_generic';
    }

    protected function initializeDoctrineTypeMappings(): void
    {
    }

    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef): string
    {
        throw $this->createNotSupportedException();
    }

    public function getBigIntTypeDeclarationSQL(array $columnDef): string
    {
        throw $this->createNotSupportedException();
    }

    public function getBlobTypeDeclarationSQL(array $field): string
    {
        throw $this->createNotSupportedException();
    }

    public function getBooleanTypeDeclarationSQL(array $columnDef): string
    {
        throw $this->createNotSupportedException();
    }

    public function getClobTypeDeclarationSQL(array $field): string
    {
        throw $this->createNotSupportedException();
    }

    public function getIntegerTypeDeclarationSQL(array $columnDef): string
    {
        throw $this->createNotSupportedException();
    }

    public function getSmallIntTypeDeclarationSQL(array $columnDef): string
    {
        throw $this->createNotSupportedException();
    }

    public function getCurrentDatabaseExpression(): string
    {
        throw $this->createNotSupportedException();
    }
}
