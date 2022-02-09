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

namespace GraphAware\Neo4j\OGM\Metadata\Factory\Xml;

use GraphAware\Neo4j\OGM\Exception\MappingException;
use GraphAware\Neo4j\OGM\Metadata\NodeAnnotationMetadata;
use GraphAware\Neo4j\OGM\Metadata\NodeEntityMetadata;
use ReflectionClass;
use SimpleXMLElement;

class NodeEntityMetadataFactory
{
    public function __construct(
        private PropertyXmlMetadataFactory $propertyXmlMetadataFactory,
        private RelationshipXmlMetadataFactory $relationshipXmlMetadataFactory,
        private IdXmlMetadataFactory $idXmlMetadataFactory
    ) {
    }

    public function buildNodeEntityMetadata(SimpleXMLElement $node, string $className): NodeEntityMetadata
    {
        $reflection = new ReflectionClass($className);

        return new NodeEntityMetadata(
            $className,
            $reflection,
            $this->buildNodeMetadata($node, $className),
            $this->idXmlMetadataFactory->buildEntityIdMetadata($node, $className, $reflection),
            $this->propertyXmlMetadataFactory->buildPropertiesMetadata($node, $className, $reflection),
            $this->relationshipXmlMetadataFactory->buildRelationshipsMetadata($node, $className, $reflection)
        );
    }

    private function buildNodeMetadata(SimpleXMLElement $node, string $className): NodeAnnotationMetadata
    {
        if (!isset($node['label'])) {
            throw new MappingException(
                sprintf('Class "%s" OGM XML node configuration is missing "label" attribute', $className)
            );
        }

        return new NodeAnnotationMetadata(
            (string) $node['label'],
            isset($node['repository-class']) ? (string) $node['repository-class'] : null
        );
    }
}
