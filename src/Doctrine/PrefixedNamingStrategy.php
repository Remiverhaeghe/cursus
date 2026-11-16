<?php

namespace App\Doctrine;

use Doctrine\ORM\Mapping\NamingStrategy;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;

/** Noms de tables et de colonnes en snake_case, sans le préfixe « a » des attributs (aFirstName devient first_name). */
final class PrefixedNamingStrategy implements NamingStrategy
{
    private UnderscoreNamingStrategy $aInner;

    public function __construct()
    {
        $this->aInner = new UnderscoreNamingStrategy();
    }

    private static function withoutPrefix(string $pPropertyName): string
    {
        $rName = preg_replace('/^a(?=[A-Z])/', '', $pPropertyName) ?? $pPropertyName;

        return $rName;
    }

    public function classToTableName(string $className): string
    {
        return $this->aInner->classToTableName($className);
    }

    public function propertyToColumnName(string $propertyName, string $className): string
    {
        return $this->aInner->propertyToColumnName(self::withoutPrefix($propertyName), $className);
    }

    public function embeddedFieldToColumnName(
        string $propertyName,
        string $embeddedColumnName,
        string $className,
        string $embeddedClassName,
    ): string {
        return $this->aInner->embeddedFieldToColumnName(
            self::withoutPrefix($propertyName),
            $embeddedColumnName,
            $className,
            $embeddedClassName,
        );
    }

    public function referenceColumnName(): string
    {
        return $this->aInner->referenceColumnName();
    }

    public function joinColumnName(string $propertyName, string $className): string
    {
        return $this->aInner->joinColumnName(self::withoutPrefix($propertyName), $className);
    }

    public function joinTableName(string $sourceEntity, string $targetEntity, string $propertyName): string
    {
        return $this->aInner->joinTableName($sourceEntity, $targetEntity, $propertyName);
    }

    public function joinKeyColumnName(string $entityName, ?string $referencedColumnName): string
    {
        return $this->aInner->joinKeyColumnName($entityName, $referencedColumnName);
    }
}
