<?php

/*
 * This file is part of the GraphAware Neo4j PHP OGM package.
 *
 * (c) GraphAware Ltd <info@graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphAware\Neo4j\OGM\Persister;

use InvalidArgumentException;
use Laudis\Neo4j\Databags\Statement;
use GraphAware\Neo4j\OGM\Metadata\RelationshipMetadata;
use RuntimeException;

class RelationshipPersister
{
    public function getRelationshipQuery($entityIdA, RelationshipMetadata $relationship, $entityIdB): Statement
    {
        if ('' === trim($relationship->getType())) {
            throw new RuntimeException('Cannot create empty relationship type');
        }

        $relString = match ($relationship->getDirection()) {
            'OUTGOING' => '-[r:%s]->',
            'INCOMING' => '<-[r:%s]-',
            'BOTH' => '-[r:%s]-',
            default => throw new \InvalidArgumentException(
                sprintf('Direction "%s" is not valid', $relationship->getDirection())
            ),
        };

        $relStringPart = sprintf($relString, $relationship->getType());

        $query = 'MATCH (a), (b) WHERE id(a) = {ida} AND id(b) = {idb}
        MERGE (a)'.$relStringPart.'(b)
        RETURN id(r)';

        return Statement::create($query, ['ida' => $entityIdA, 'idb' => $entityIdB]);
    }

    public function getDeleteRelationshipQuery($entityIdA, $entityIdB, RelationshipMetadata $relationship): Statement
    {
        $relString = match ($relationship->getDirection()) {
            'OUTGOING' => '-[r:%s]->',
            'INCOMING' => '<-[r:%s]-',
            'BOTH' => '-[r:%s]-',
            default => throw new InvalidArgumentException(
                sprintf('Direction "%s" is not valid', $relationship->getDirection())
            ),
        };

        $relStringPart = sprintf($relString, $relationship->getType());

        $query = 'MATCH (a), (b) WHERE id(a) = {ida} AND id(b) = {idb}
        MATCH (a)'.$relStringPart.'(b)
        DELETE r';

        return Statement::create($query, ['ida' => $entityIdA, 'idb' => $entityIdB]);
    }
}
