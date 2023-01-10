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

namespace GraphAware\Neo4j\OGM\Persisters;

use GraphAware\Neo4j\OGM\Metadata\RelationshipMetadata;
use InvalidArgumentException;
use Laudis\Neo4j\Databags\Statement;
use RuntimeException;

class RelationshipPersister
{
    private string $paramStyle;

    public function __construct(bool $isV4 = false)
    {
        $this->paramStyle = $isV4 ? '$%s' : '{%s}';
    }

    public function getRelationshipQuery($entityIdA, RelationshipMetadata $relationship, $entityIdB): Statement
    {
        if ('' === trim($relationship->getType())) {
            throw new RuntimeException('Cannot create empty relationship type');
        }

        $relString = match ($relationship->getDirection()) {
            'OUTGOING' => '-[r:%s]->',
            'INCOMING' => '<-[r:%s]-',
            'BOTH' => '-[r:%s]-',
            default => throw new InvalidArgumentException(
                sprintf('Direction "%s" is not valid', $relationship->getDirection())
            ),
        };

        $query = sprintf(
            "MATCH (a), (b) WHERE id(a) = {$this->paramStyle} AND id(b) = {$this->paramStyle}"
            . ' MERGE (a) %s (b) RETURN id(r)',
            'ida',
            'idb',
            sprintf($relString, $relationship->getType())
        );

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

        $query = sprintf(
            "MATCH (a), (b) WHERE id(a) = {$this->paramStyle} AND id(b) = {$this->paramStyle}"
            . ' MATCH (a) %s (b) DELETE r',
            'ida',
            'idb',
            sprintf($relString, $relationship->getType())
        );

        return Statement::create($query, ['ida' => $entityIdA, 'idb' => $entityIdB]);
    }
}
