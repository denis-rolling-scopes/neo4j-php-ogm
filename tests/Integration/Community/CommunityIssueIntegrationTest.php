<?php

/*
 * This file is part of the GraphAware Neo4j PHP OGM package.
 *
 * (c) GraphAware Ltd <info@graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphAware\Neo4j\OGM\Tests\Integration\Community;

use GraphAware\Neo4j\OGM\Tests\Integration\IntegrationTestCase;
use GraphAware\Neo4j\OGM\Tests\Integration\Models\EntityWithSimpleRelationship\Car;
use GraphAware\Neo4j\OGM\Tests\Integration\Models\EntityWithSimpleRelationship\Person;

class CommunityIssueIntegrationTest extends IntegrationTestCase
{
    public function testMyIssue()
    {
        // Clear database
        $this->clearDb();

        $person = new Person('Mike');
        $car = new Car('Bugatti', $person);
        $person->setCar($car);
        $this->em->persist($person);
        $this->em->flush();

        $person->setName('Mike2');
        $this->em->flush();

        $result = $this->client->run('MATCH (n:Person {name:"Mike2"})-[:OWNS]->(c:Car {model:"Bugatti"}) RETURN n, c');
        $this->assertEquals(1, $result->count());
    }
}
