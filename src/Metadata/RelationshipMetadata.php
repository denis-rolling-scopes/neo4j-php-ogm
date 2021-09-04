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

use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\ArrayCollection;
use GraphAware\Neo4j\OGM\Annotations\OrderBy;
use GraphAware\Neo4j\OGM\Annotations\Relationship;
use GraphAware\Neo4j\OGM\Common\Collection;
use GraphAware\Neo4j\OGM\Exception\MappingException;
use GraphAware\Neo4j\OGM\Proxy\LazyCollection;
use GraphAware\Neo4j\OGM\Util\ClassUtils;
use ReflectionProperty;

final class RelationshipMetadata
{
    private string $propertyName;

    public function __construct(
        private string $className,
        private ReflectionProperty $reflectionProperty,
        private Relationship $relationshipAnnotation,
        private bool $isLazy = false,
        private ?bool $isFetch = false,
        private ?OrderBy $orderBy = null
    ) {
        $this->propertyName = $reflectionProperty->getName();
        if (null !== $orderBy) {
            if (!in_array($orderBy->order, ['ASC', 'DESC'], true)) {
                throw new MappingException(sprintf('The order "%s" is not valid', $orderBy->order));
            }
        }
    }

    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    public function getReflectionProperty(): ReflectionProperty
    {
        return $this->reflectionProperty;
    }

    public function getType(): string
    {
        return $this->relationshipAnnotation->type;
    }

    public function isRelationshipEntity(): bool
    {
        return null !== $this->relationshipAnnotation->relationshipEntity;
    }

    public function isTargetEntity(): bool
    {
        return null !== $this->relationshipAnnotation->targetEntity;
    }

    public function isCollection(): bool
    {
        return true === $this->relationshipAnnotation->collection;
    }

    public function isLazy(): bool
    {
        return $this->isLazy;
    }

    public function isFetch(): ?bool
    {
        return $this->isFetch;
    }

    public function getDirection(): string
    {
        return $this->relationshipAnnotation->direction;
    }

    public function getTargetEntity(): string
    {
        return ClassUtils::getFullClassName($this->relationshipAnnotation->targetEntity, $this->className);
    }

    public function getRelationshipEntityClass(): string
    {
        return ClassUtils::getFullClassName($this->relationshipAnnotation->relationshipEntity, $this->className);
    }

    public function hasMappedByProperty(): bool
    {
        return null !== $this->relationshipAnnotation->mappedBy;
    }

    public function getMappedByProperty(): ?string
    {
        return $this->relationshipAnnotation->mappedBy;
    }

    public function hasOrderBy(): bool
    {
        return null !== $this->orderBy;
    }

    public function getOrderByProperty(): string
    {
        return $this->orderBy->property;
    }

    public function getOrder(): string
    {
        return $this->orderBy->order;
    }

    public function initializeCollection(object $object)
    {
        if (!$this->isCollection()) {
            throw new \LogicException(sprintf('The property mapping this relationship is not of collection type in "%s"', $this->className));
        }
        if (is_array($this->getValue($object)) && !empty($this->getValue($object))) {
            $this->setValue($object, new ArrayCollection($this->getValue($object)));

            return;
        }
        if ($this->getValue($object) instanceof ArrayCollection || $this->getValue($object) instanceof AbstractLazyCollection) {
            return;
        }
        $this->setValue($object, new Collection());
    }

    public function addToCollection(object $object, mixed $value)
    {
        if (!$this->isCollection()) {
            throw new \LogicException(sprintf('The property mapping of this relationship is not of collection type in "%s"', $this->className));
        }

        $coll = $this->getValue($object);

        if ($coll instanceof LazyCollection) {
            return $coll->add($value, false);
        }

        if (null === $coll) {
            $coll = new Collection();
            $this->setValue($object, $coll);
        }
        $toAdd = true;
        $oid2 = spl_object_hash($value);
        foreach ($coll->toArray() as $el) {
            $oid1 = spl_object_hash($el);
            if ($oid1 === $oid2) {
                $toAdd = false;
            }
        }

        if ($toAdd) {
            $coll->add($value);
        }
    }

    public function getValue(object $object): mixed
    {
        $this->reflectionProperty->setAccessible(true);

        return $this->reflectionProperty->getValue($object);
    }

    public function setValue(object $object, mixed $value)
    {
        $this->reflectionProperty->setAccessible(true);
        $this->reflectionProperty->setValue($object, $value);
    }

    public function getAlias(): string
    {
        return strtolower(str_replace('\\', '_', $this->className) . '_' . $this->propertyName);
    }
}
