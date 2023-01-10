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

use GraphAware\Neo4j\OGM\Metadata\RelationshipMetadata;

class RelationshipEntityCollectionInitializer extends RelationshipEntityInitializer
{
    public function initialize($baseInstance)
    {
        $persist = $this->em->getEntityPersister($this->metadata->getClassName());
        $persist->getRelationshipEntityCollection($this->relationshipMetadata->getPropertyName(), $baseInstance);
    }

    public function getCount($baseInstance, RelationshipMetadata $relationshipMetadata)
    {
        $persist = $this->em->getEntityPersister($this->metadata->getClassName());

        return $persist->getCountForRelationship($relationshipMetadata->getPropertyName(), $baseInstance);
    }
}
