<?php

declare(strict_types=1);

/*
 * This file is part of the GraphAware Neo4j PHP OGM package.
 *
 * (c) GraphAware Ltd <info@graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphAware\Neo4j\OGM\Metadata;

use GraphAware\Neo4j\OGM\Annotations\RelationshipEntity;
use ReflectionClass;
use ReflectionProperty;

final class RelationshipEntityMetadata extends GraphEntityMetadata
{
    private string $type;

    private string $startNodeEntityMetadata;

    private ReflectionProperty $startNodeReflectionProperty;

    private ReflectionProperty $endNodeReflectionProperty;

    private string $endNodeEntityMetadata;

    public function __construct(
        $class,
        ReflectionClass $reflectionClass,
        RelationshipEntity $annotation,
        EntityIdMetadata $entityIdMetadata,
        string $startNodeClass,
        mixed $startNodeKey,
        string $endNodeClass,
        mixed $endNodeKey,
        array $entityPropertiesMetadata
    ) {
        parent::__construct($entityIdMetadata, $class, $reflectionClass, $entityPropertiesMetadata);
        $this->startNodeEntityMetadata = $startNodeClass;
        $this->endNodeEntityMetadata = $endNodeClass;
        $this->type = $annotation->type;
        $this->startNodeReflectionProperty = $this->reflectionClass->getProperty($startNodeKey);
        $this->endNodeReflectionProperty = $this->reflectionClass->getProperty($endNodeKey);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getStartNode(): string
    {
        return $this->startNodeEntityMetadata;
    }

    public function getEndNode(): string
    {
        return $this->endNodeEntityMetadata;
    }

    public function getStartNodePropertyName(): string
    {
        return $this->startNodeReflectionProperty->getName();
    }

    public function setStartNodeProperty($object, $value): void
    {
        $this->startNodeReflectionProperty->setAccessible(true);
        $this->startNodeReflectionProperty->setValue($object, $value);
    }

    public function getStartNodeValue($object)
    {
        $this->startNodeReflectionProperty->setAccessible(true);

        return $this->startNodeReflectionProperty->getValue($object);
    }

    public function getEndNodePropertyName(): string
    {
        return $this->endNodeReflectionProperty->getName();
    }

    public function setEndNodeProperty($object, $value): void
    {
        $this->endNodeReflectionProperty->setAccessible(true);
        $this->endNodeReflectionProperty->setValue($object, $value);
    }

    public function getEndNodeProperty($object): mixed
    {
        $this->endNodeReflectionProperty->setAccessible(true);

        return $this->endNodeReflectionProperty->getValue($object);
    }

    public function getEndNodeValue($object): mixed
    {
        $this->endNodeReflectionProperty->setAccessible(true);

        return $this->endNodeReflectionProperty->getValue($object);
    }

    public function hasAssociation($fieldName): bool
    {
        return $fieldName === $this->startNodeReflectionProperty->getName()
        || $fieldName === $this->endNodeReflectionProperty->getName();
    }

    public function isSingleValuedAssociation($fieldName): bool
    {
        return $fieldName === $this->startNodeReflectionProperty->getName()
            || $fieldName === $this->endNodeReflectionProperty->getName();
    }

    public function isCollectionValuedAssociation($fieldName): bool
    {
        return false;
    }

    public function getAssociationNames(): array
    {
        return [
            $this->startNodeReflectionProperty->getName(),
            $this->endNodeReflectionProperty->getName(),
        ];
    }

    public function getAssociationTargetClass($assocName): ?string
    {
        if ($this->startNodeReflectionProperty->getName() === $assocName) {
            return $this->startNodeEntityMetadata;
        }

        if ($this->endNodeReflectionProperty->getName() === $assocName) {
            return $this->endNodeEntityMetadata;
        }

        return null;
    }

    public function getOtherClassNameForOwningClass($class): string
    {
        if ($this->startNodeEntityMetadata === $class) {
            return $this->endNodeEntityMetadata;
        }

        return $this->startNodeEntityMetadata;
    }

    public function getInversedSide($name): ReflectionProperty
    {
        if ($this->startNodeReflectionProperty->getName() === $name) {
            return $this->endNodeReflectionProperty;
        }

        return $this->startNodeReflectionProperty;
    }

    public function getStartNodeClass(): string
    {
        return $this->startNodeEntityMetadata;
    }

    public function getEndNodeClass(): string
    {
        return $this->endNodeEntityMetadata;
    }

    public function isAssociationInverseSide($assocName): bool
    {
        // Not implemented
        return false;
    }

    public function getAssociationMappedByTargetField($assocName): ?string
    {
        // TODO: Implement getAssociationMappedByTargetField() method.

        return null;
    }
}
