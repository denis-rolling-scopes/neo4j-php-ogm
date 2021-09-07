<?php

/*
 * This file is part of the GraphAware Neo4j PHP OGM package.
 *
 * (c) GraphAware Ltd <info@graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphAware\Neo4j\OGM\Metadata\Factory\Xml;

use Doctrine\Persistence\Mapping\Driver\FileLocator;
use GraphAware\Neo4j\OGM\Exception\MappingException;
use GraphAware\Neo4j\OGM\Metadata\Factory\GraphEntityMetadataFactoryInterface;
use GraphAware\Neo4j\OGM\Metadata\NodeEntityMetadata;
use GraphAware\Neo4j\OGM\Metadata\RelationshipEntityMetadata;

class XmlGraphEntityMetadataFactory implements GraphEntityMetadataFactoryInterface
{
    public function __construct(
        private FileLocator                       $fileLocator,
        private NodeEntityMetadataFactory         $nodeEntityMetadataFactory,
        private RelationshipEntityMetadataFactory $relationshipEntityMetadataFactory
    ) {
    }

    public function create($className): RelationshipEntityMetadata|NodeEntityMetadata
    {
        $xml = $this->getXmlInstance($className);

        if (isset($xml->node)) {
            $this->validateEntityClass($xml->node, $className);

            return $this->nodeEntityMetadataFactory->buildNodeEntityMetadata($xml->node, $className);
        } elseif (isset($xml->relationship)) {
            $this->validateEntityClass($xml->relationship, $className);

            return $this->relationshipEntityMetadataFactory
                ->buildRelationshipEntityMetadata($xml->relationship, $className);
        }

        throw new MappingException(sprintf('Invalid OGM XML configuration for class "%s"', $className));
    }

    public function supports($className): bool
    {
        return $this->fileLocator->fileExists($className);
    }

    public function createQueryResultMapper($className)
    {
        // TODO: Implement createQueryResultMapper() method.
    }

    public function supportsQueryResult($className): bool
    {
        return false;
    }

    private function getXmlInstance($className): \SimpleXMLElement
    {
        $filename = $this->fileLocator->findMappingFile($className);

        return new \SimpleXMLElement(file_get_contents($filename));
    }

    /**
     * @param \SimpleXMLElement $element
     * @param string $className
     */
    private function validateEntityClass(\SimpleXMLElement $element, string $className)
    {
        if (!isset($element['entity']) || (string)$element['entity'] !== $className) {
            throw new MappingException(
                sprintf('Class "%s" OGM XML configuration has invalid or missing "entity" element', $className)
            );
        }
    }
}
