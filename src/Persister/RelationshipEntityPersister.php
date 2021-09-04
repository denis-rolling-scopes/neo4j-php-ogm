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
use GraphAware\Neo4j\OGM\Metadata\RelationshipEntityMetadata;
use GraphAware\Neo4j\OGM\Util\ClassUtils;

class RelationshipEntityPersister
{
    public function __construct(
        protected EntityManager $manager,
        protected string $className,
        protected RelationshipEntityMetadata $classNameMetadata
    ) {
    }

    public function getCreateQuery($entity, $pov): Statement
    {
        $class = ClassUtils::getFullClassName(get_class($entity), $pov);
        $relationshipEntityMetadata = $this->manager->getRelationshipEntityMetadata($class);
        $startNode = $relationshipEntityMetadata->getStartNodeValue($entity);
        $startNodeId = $this->manager->getClassMetadataFor(get_class($startNode))->getIdValue($startNode);
        $endNode = $relationshipEntityMetadata->getEndNodeValue($entity);
        $endNodeId = $this->manager->getClassMetadataFor(get_class($endNode))->getIdValue($endNode);

        $relType = $this->classNameMetadata->getType();
        $parameters = [
            'a' => $startNodeId,
            'b' => $endNodeId,
            'fields' => [],
        ];

        foreach ($this->classNameMetadata->getPropertiesMetadata() as $field => $propertyMetadata) {
            $v = $propertyMetadata->getValue($entity);
            $fieldKey = $field;

            if ($propertyMetadata->getPropertyAnnotationMetadata()->hasCustomKey()) {
                $fieldKey = $propertyMetadata->getPropertyAnnotationMetadata()->getKey();
            }

            $parameters['fields'][$fieldKey] = $v;
        }

        $parameters = $this->getParameters($entity, $parameters);

        $query = 'MATCH (a), (b) WHERE id(a) = {a} AND id(b) = {b}' . PHP_EOL;
        $query .= sprintf('CREATE (a)-[r:%s]->(b)', $relType) . PHP_EOL;
        if (!empty($parameters['fields'])) {
            $query .= 'SET r += {fields} ';
        }
        $query .= 'RETURN id(r) AS id, {oid} AS oid';
        $parameters['oid'] = spl_object_hash($entity);

        return Statement::create($query, $parameters);
    }

    public function getUpdateQuery($entity): Statement
    {
        $id = $this->classNameMetadata->getIdValue($entity);

        $query = sprintf('MATCH ()-[rel]->() WHERE id(rel) = %d SET rel += {fields}', $id);

        $parameters = [
            'fields' => [],
        ];

        $parameters = $this->getParameters($entity, $parameters);

        return Statement::create($query, $parameters);
    }

    public function getDeleteQuery($entity): Statement
    {
        $id = $this->classNameMetadata->getIdValue($entity);
        $query = 'START rel=rel(' . $id . ') DELETE rel RETURN {oid} AS oid';
        $params = ['oid' => spl_object_hash($entity)];

        return Statement::create($query, $params);
    }

    public function getParameters($entity, array $parameters): array
    {
        foreach ($this->classNameMetadata->getPropertiesMetadata() as $field => $meta) {
            $fieldId = $this->classNameMetadata->getClassName() . $field;
            $fieldKey = $field;

            if ($meta->getPropertyAnnotationMetadata()->hasCustomKey()) {
                $fieldKey = $meta->getPropertyAnnotationMetadata()->getKey();
            }

            if ($meta->hasConverter()) {
                $converter = Converter::getConverter($meta->getConverterType(), $fieldId);
                $v = $converter->toDatabaseValue($meta->getValue($entity), $meta->getConverterOptions());
                $parameters['fields'][$fieldKey] = $v;
            } else {
                $parameters['fields'][$fieldKey] = $meta->getValue($entity);
            }
        }
        return $parameters;
    }
}
