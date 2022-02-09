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

use DateTime;
use Doctrine\Persistence\Mapping\ClassMetadata;
use ReflectionClass;

abstract class GraphEntityMetadata implements ClassMetadata
{
    protected array $entityPropertiesMetadata = [];

    public function __construct(
        protected EntityIdMetadata $entityIdMetadata,
        protected string $className,
        protected ReflectionClass $reflectionClass,
        array $entityPropertiesMetadata
    ) {
        foreach ($entityPropertiesMetadata as $meta) {
            if ($meta instanceof EntityPropertyMetadata) {
                $this->entityPropertiesMetadata[$meta->getPropertyName()] = $meta;
            }
        }
    }

    public function getName(): string
    {
        return $this->className;
    }

    public function getReflectionClass(): ReflectionClass
    {
        return $this->reflectionClass;
    }

    public function isIdentifier($fieldName): bool
    {
        return $this->entityIdMetadata->getPropertyName() === $fieldName;
    }

    public function hasField($fieldName): bool
    {
        foreach ($this->entityPropertiesMetadata as $entityPropertyMetadata) {
            if ($entityPropertyMetadata->getPropertyName() === $fieldName) {
                return true;
            }
        }

        return false;
    }

    public function getFieldNames(): array
    {
        $fields = [];
        $fields[] = $this->entityIdMetadata->getPropertyName();
        foreach ($this->entityPropertiesMetadata as $entityPropertyMetadata) {
            $fields[] = $entityPropertyMetadata->getPropertyName();
        }

        return $fields;
    }

    public function getIdentifierFieldNames(): array
    {
        return [$this->entityIdMetadata->getPropertyName()];
    }

    public function getTypeOfField($fieldName): ?string
    {
        // TODO: Implement getTypeOfField() method.
        return null;
    }

    public function getIdentifierValues($object): array
    {
        return [$this->getIdValue($object)];
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function newInstance(): object
    {
        return $this->reflectionClass->newInstanceWithoutConstructor();
    }

    public function getIdValue(object $object): mixed
    {
        return $this->entityIdMetadata->getValue($object);
    }

    public function setId(object $object, string|int $value)
    {
        $this->entityIdMetadata->setValue($object, $value);
    }

    public function getIdentifier(): string|array
    {
        return $this->entityIdMetadata->getPropertyName();
    }

    public function getPropertiesMetadata(): array
    {
        return $this->entityPropertiesMetadata;
    }

    public function getPropertyMetadata($key): ?EntityPropertyMetadata
    {
        if (array_key_exists($key, $this->entityPropertiesMetadata)) {
            return $this->entityPropertiesMetadata[$key];
        }

        return null;
    }

    public function getPropertyValuesArray(object $object): array
    {
        $values = [];
        foreach ($this->entityPropertiesMetadata as $entityPropertyMetadata) {
            $value = $entityPropertyMetadata->getValue($object);
            $value = ($value instanceof DateTime) ? $value->getTimestamp() : $value;
            $values[$entityPropertyMetadata->getPropertyName()] = $value;
        }

        return $values;
    }

    public function getEntityAlias(): string
    {
        return strtolower(str_replace('\\', '_', $this->className));
    }
}
