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

namespace GraphAware\Neo4j\OGM\Metadata\Factory\Annotation;

use Doctrine\Common\Annotations\Reader;
use GraphAware\Neo4j\OGM\Annotations\Node;
use GraphAware\Neo4j\OGM\Exception\MappingException;
use GraphAware\Neo4j\OGM\Metadata\NodeAnnotationMetadata;
use ReflectionClass;

final class NodeAnnotationMetadataFactory
{
    public function __construct(private Reader $reader)
    {
    }

    public function create(string $nodeEntityClass): NodeAnnotationMetadata
    {
        $reflectionClass = new ReflectionClass($nodeEntityClass);
        /** @var ?Node $annotation */
        $annotation = $this->reader->getClassAnnotation($reflectionClass, Node::class);

        if (null !== $annotation) {
            return new NodeAnnotationMetadata($annotation->label, $annotation->repository);
        } else {
            throw new MappingException(
                sprintf('The class "%s" is missing the "%s" annotation', $nodeEntityClass, Node::class)
            );
        }
    }
}
