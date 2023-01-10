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

use GraphAware\Neo4j\OGM\Util\ClassUtils;
use ReflectionClass;

final class NodeEntityMetadata extends GraphEntityMetadata
{
    protected array $labeledPropertiesMetadata = [];

    protected array $relationships = [];

    private ?string $customRepository;


    public function __construct(
        $className,
        ReflectionClass $reflectionClass,
        private NodeAnnotationMetadata $nodeAnnotationMetadata,
        EntityIdMetadata $entityIdMetadata,
        array $entityPropertiesMetadata,
        array $simpleRelationshipsMetadata
    ) {
        parent::__construct($entityIdMetadata, $className, $reflectionClass, $entityPropertiesMetadata);
        $this->customRepository = $this->nodeAnnotationMetadata->getCustomRepository();
        foreach ($entityPropertiesMetadata as $o) {
            if ($o instanceof LabeledPropertyMetadata) {
                $this->labeledPropertiesMetadata[$o->getPropertyName()] = $o;
            }
        }
        foreach ($simpleRelationshipsMetadata as $relationshipMetadata) {
            $this->relationships[$relationshipMetadata->getPropertyName()] = $relationshipMetadata;
        }
    }

    public function getLabel(): string
    {
        return $this->nodeAnnotationMetadata->getLabel();
    }

    public function getLabeledProperty($key): ?LabeledPropertyMetadata
    {
        if (array_key_exists($key, $this->labeledPropertiesMetadata)) {
            return $this->labeledPropertiesMetadata[$key];
        }
        return null;
    }

    public function getLabeledProperties(): array
    {
        return $this->labeledPropertiesMetadata;
    }

    public function getLabeledPropertiesToBeSet($object): array
    {
        return array_filter($this->getLabeledProperties(), function (LabeledPropertyMetadata $labeledPropertyMetadata) use ($object) {
            return true === $labeledPropertyMetadata->getValue($object);
        });
    }

    public function hasCustomRepository(): bool
    {
        return null !== $this->customRepository;
    }

    public function getRepositoryClass(): string
    {
        if (null === $this->customRepository) {
            throw new \LogicException(sprintf('There is no custom repository for "%s"', $this->className));
        }

        return ClassUtils::getFullClassName($this->customRepository, $this->className);
    }

    public function getRelationships(): array
    {
        return $this->relationships;
    }

    /**
     * Returns non-lazy relationships.
     * Note that currently relationships that are not of type "collection" are considered non-lazy.
     *
     * @return RelationshipMetadata[]
     */
    public function getNonLazyRelationships(): array
    {
        $rels = [];
        foreach ($this->relationships as $relationship) {
            if (!$relationship->isLazy()) {
                $rels[] = $relationship;
            }
        }

        return $rels;
    }

    public function getLazyRelationships(mixed $andRelEntities = false): array
    {
        $rels = [];
        foreach ($this->relationships as $relationship) {
            if ($relationship->isLazy()) {
                if ($relationship->isRelationshipEntity() && !$andRelEntities) {
                    continue;
                }
                $rels[] = $relationship;
            }
        }

        return $rels;
    }

    public function getFetchRelationships(bool $andRelationshipEntities = false): array
    {
        $rels = [];
        foreach ($this->relationships as $relationship) {
            if ($relationship->isFetch() && !$relationship->isRelationshipEntity()) {
                $rels[] = $relationship;
            }
        }

        return $rels;
    }

    public function getRelationship($key): ?RelationshipMetadata
    {
        if (array_key_exists($key, $this->relationships)) {
            return $this->relationships[$key];
        }

        return null;
    }

    public function getSimpleRelationships(mixed $andLazy = true): array
    {
        $coll = [];
        foreach ($this->relationships as $relationship) {
            if (!$relationship->isRelationshipEntity() && (!$relationship->isLazy() || $relationship->isLazy() === $andLazy)) {
                $coll[] = $relationship;
            }
        }

        return $coll;
    }

    public function getRelationshipEntities(): array
    {
        $coll = [];
        foreach ($this->relationships as $relationship) {
            if ($relationship->isRelationshipEntity()) {
                $coll[] = $relationship;
            }
        }

        return $coll;
    }

    public function getAssociatedObjects(): array
    {
        return $this->getSimpleRelationships();
    }

    public function hasAssociation($fieldName): bool
    {
        foreach ($this->relationships as $relationship) {
            if ($relationship->getPropertyName() === $fieldName) {
                return true;
            }
        }

        return false;
    }

    public function isSingleValuedAssociation($fieldName): bool
    {
        foreach ($this->relationships as $relationship) {
            if ($relationship->getPropertyName() === $fieldName && !$relationship->isCollection()) {
                return true;
            }
        }

        return false;
    }

    public function isCollectionValuedAssociation($fieldName): bool
    {
        foreach ($this->relationships as $relationship) {
            if ($relationship->getPropertyName() === $fieldName && $relationship->isCollection()) {
                return true;
            }
        }

        return false;
    }

    public function getAssociationNames(): array
    {
        $names = [];
        foreach ($this->relationships as $relationship) {
            $names[] = $relationship->getPropertyName();
        }

        return $names;
    }

    public function getAssociationTargetClass($assocName): ?string
    {
        foreach ($this->relationships as $relationship) {
            if ($relationship->getPropertyName() === $assocName) {
                if ($relationship->isRelationshipEntity()) {
                    return $relationship->getRelationshipEntityClass();
                }

                return $relationship->getTargetEntity();
            }
        }

        return null;
    }

    public function isAssociationInverseSide($assocName): bool
    {
        // is not implemented in the context of the ogm.
        // if entities should be hydrated on the inversed entity, the only mappedBy annotation property should be used.

        return false;
    }

    public function getAssociationMappedByTargetField($assocName): ?string
    {
        foreach ($this->relationships as $relationship) {
            if ($relationship->hasMappedByProperty() && $relationship->getMappedByProperty() === $assocName) {
                return $relationship->getPropertyName();
            }
        }

        return null;
    }

    public function getMappedByFieldsForFetch(): array
    {
        $fields = [];
        foreach ($this->getFetchRelationships() as $relationship) {
            if ($relationship->hasMappedByProperty()) {
                $fields[] = $relationship->getMappedByProperty();
            }
        }

        return $fields;
    }
}
