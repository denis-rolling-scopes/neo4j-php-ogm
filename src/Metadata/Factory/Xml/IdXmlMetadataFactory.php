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
use GraphAware\Neo4j\OGM\Metadata\EntityIdMetadata;
use GraphAware\Neo4j\OGM\Metadata\IdAnnotationMetadata;
use ReflectionClass;

class IdXmlMetadataFactory
{
    public function buildEntityIdMetadata(
        \SimpleXMLElement $node,
        string $className,
        ReflectionClass $reflection
    ): EntityIdMetadata {
        if (!isset($node->id) || !isset($node->id['name'])) {
            throw new MappingException(
                sprintf('Class "%s" OGM XML configuration has invalid or missing "id" element', $className)
            );
        }

        return new EntityIdMetadata(
            (string) $node->id['name'],
            $reflection->getProperty((string) $node->id['name'])
        );
    }
}
