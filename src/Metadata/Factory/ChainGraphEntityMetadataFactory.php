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

namespace GraphAware\Neo4j\OGM\Metadata\Factory;

use GraphAware\Neo4j\OGM\Exception\MappingException;
use GraphAware\Neo4j\OGM\Exception\MetadataFactoryException;
use GraphAware\Neo4j\OGM\Metadata\NodeEntityMetadata;
use GraphAware\Neo4j\OGM\Metadata\QueryResultMapper;
use GraphAware\Neo4j\OGM\Metadata\RelationshipEntityMetadata;

class ChainGraphEntityMetadataFactory implements GraphEntityMetadataFactoryInterface
{
    private array $innerFactories;

    public function __construct()
    {
        $this->innerFactories = [];
    }

    public function addMetadataFactory(GraphEntityMetadataFactoryInterface $factory, $priority)
    {
        if (isset($this->innerFactories[$priority])) {
            throw new MetadataFactoryException(
                sprintf('MetadataFactory with priority "%d" already registered', $priority)
            );
        }

        $this->innerFactories[$priority] = $factory;
        ksort($this->innerFactories);
    }

    public function create($className): RelationshipEntityMetadata|NodeEntityMetadata
    {
        foreach ($this->innerFactories as $innerFactory) {
            if ($innerFactory->supports($className)) {
                return $innerFactory->create($className);
            }
        }

        throw new MappingException(sprintf('The class "%s" is not a valid OGM entity', $className));
    }

    public function supports($className): bool
    {
        foreach ($this->innerFactories as $innerFactory) {
            if ($innerFactory->supports($className)) {
                return true;
            }
        }

        return false;
    }

    public function createQueryResultMapper($className): QueryResultMapper
    {
        foreach ($this->innerFactories as $innerFactory) {
            if ($innerFactory->supportsQueryResult($className)) {
                return $innerFactory->createQueryResultMapper($className);
            }
        }

        throw new MappingException(sprintf('The class "%s" is not a valid QueryResult entity', $className));
    }

    public function supportsQueryResult($className): bool
    {
        foreach ($this->innerFactories as $innerFactory) {
            if ($innerFactory->supportsQueryResult($className)) {
                return true;
            }
        }

        return false;
    }
}
