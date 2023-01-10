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

namespace GraphAware\Neo4j\OGM\Proxy;

use GraphAware\Neo4j\OGM\EntityManager;
use GraphAware\Neo4j\OGM\Metadata\NodeEntityMetadata;
use GraphAware\Neo4j\OGM\Metadata\RelationshipMetadata;

class SingleNodeInitializer
{
    public function __construct(
        protected EntityManager $em,
        protected RelationshipMetadata $relationshipMetadata,
        protected NodeEntityMetadata $metadata
    ) {
    }

    public function initialize($baseInstance)
    {
        $persister = $this->em->getEntityPersister($this->metadata->getClassName());
        $persister->getSimpleRelationship($this->relationshipMetadata->getPropertyName(), $baseInstance);
    }
}
