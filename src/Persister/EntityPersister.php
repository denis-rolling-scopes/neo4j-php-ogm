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

namespace GraphAware\Neo4j\OGM\Persister;

use Laudis\Neo4j\Databags\Statement;
use GraphAware\Neo4j\OGM\Converters\Converter;
use GraphAware\Neo4j\OGM\EntityManager;
use GraphAware\Neo4j\OGM\Metadata\NodeEntityMetadata;

class EntityPersister
{
    protected string $paramStyle;
    public function __construct(
        protected EntityManager $entityManager,
        protected string $className,
        protected NodeEntityMetadata $classMetadata
    ) {
        $this->paramStyle = $this->entityManager->isV4() === true ? '$%s' : '{%s}';
    }

    public function getCreateQuery($object)
    {
        [$propertyValues, $extraLabels, $removeLabels] = $this->getBaseQueryProperties($object);

        $query = sprintf('CREATE (n:%s)', $this->classMetadata->getLabel());
        if (!empty($propertyValues)) {
            $query .= sprintf(" SET n += $this->paramStyle", 'properties');
        }
        if (!empty($extraLabels)) {
            foreach ($extraLabels as $label) {
                $query .= ' SET n:' . $label;
            }
        }
        if (!empty($removeLabels)) {
            foreach ($removeLabels as $label) {
                $query .= ' REMOVE n:' . $label;
            }
        }

        $query .= ' RETURN id(n) as id';

        return Statement::create($query, ['properties' => $propertyValues]);
    }

    public function getUpdateQuery($object)
    {
        [$propertyValues, $extraLabels, $removeLabels] = $this->getBaseQueryProperties($object);

        $id = $this->classMetadata->getIdValue($object);
        $query = sprintf("MATCH (n) WHERE id(n) = $this->paramStyle SET n += $this->paramStyle", 'id', 'props');
        if (!empty($extraLabels)) {
            foreach ($extraLabels as $label) {
                $query .= ' SET n:' . $label;
            }
        }
        if (!empty($removeLabels)) {
            foreach ($removeLabels as $label) {
                $query .= ' REMOVE n:' . $label;
            }
        }

        return Statement::create($query, ['id' => $id, 'props' => $propertyValues]);
    }

    /**
     * Refreshes a managed entity.
     *
     * @param int $id
     * @param object $entity The entity to refresh
     */
    public function refresh(int $id, object $entity)
    {
        $label = $this->classMetadata->getLabel();
        $query = sprintf("MATCH (n:%s) WHERE id(n) = $this->paramStyle RETURN n", $label, 'id');
        $result = $this->entityManager->getDatabaseDriver()->run($query, ['id' => $id]);

        if ($result->count() > 0) {
            $node = $result->first()->get('n');
            $this->entityManager->getEntityHydrator($this->className)->refresh($node, $entity);
        }
    }

    public function getDetachDeleteQuery($object): Statement
    {
        $query = sprintf("MATCH (n) WHERE id(n) = $this->paramStyle DETACH DELETE n", 'id');
        $id = $this->classMetadata->getIdValue($object);

        return Statement::create($query, ['id' => $id]);
    }

    public function getDeleteQuery($object): Statement
    {
        $query = sprintf("MATCH (n) WHERE id(n) = $this->paramStyle DELETE n", 'id');
        $id = $this->classMetadata->getIdValue($object);

        return Statement::create($query, ['id' => $id]);
    }

    private function getBaseQueryProperties($object)
    {
        $propertyValues = [];
        $extraLabels = [];
        $removeLabels = [];
        foreach ($this->classMetadata->getPropertiesMetadata() as $field => $meta) {
            $fieldId = $this->classMetadata->getClassName() . $field;
            $fieldKey = $field;

            if ($meta->getPropertyAnnotationMetadata()->hasCustomKey()) {
                $fieldKey = $meta->getPropertyAnnotationMetadata()->getKey();
            }

            if ($meta->hasConverter()) {
                $converter = Converter::getConverter($meta->getConverterType(), $fieldId);
                $v = $converter->toDatabaseValue($meta->getValue($object), $meta->getConverterOptions());
                $propertyValues[$fieldKey] = $v;
            } else {
                $propertyValues[$fieldKey] = $meta->getValue($object);
            }
        }

        foreach ($this->classMetadata->getLabeledProperties() as $labeledProperty) {
            if ($labeledProperty->isLabelSet($object)) {
                $extraLabels[] = $labeledProperty->getLabelName();
            } else {
                $removeLabels[] = $labeledProperty->getLabelName();
            }
        }

        return [$propertyValues, $extraLabels, $removeLabels];
    }
}
