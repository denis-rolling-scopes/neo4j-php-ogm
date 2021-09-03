<?php

/*
 * This file is part of the GraphAware Neo4j PHP OGM package.
 *
 * (c) GraphAware Ltd <info@graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphAware\Neo4j\OGM\Tests\Integration;

use GraphAware\Neo4j\OGM\Exception\OGMInvalidArgumentException;
use GraphAware\Neo4j\OGM\Tests\Integration\Models\Base\User;

class UnitOfWorkTest extends IntegrationTestCase
{
    public function testContains()
    {
        $user = new User('neo', 33);
        $this->assertFalse($this->em->contains($user));

        $this->em->persist($user);
        $this->assertTrue($this->em->contains($user));

        $this->em->flush();
        $this->assertTrue($this->em->contains($user));
    }


    public function testRefresh()
    {
        $user = new User('neo', 33);
        $this->em->persist($user);
        $this->em->flush();

        $user->setAge(55);
        $this->em->refresh($user);

        $this->assertEquals(33, $user->getAge(), 'Could not refresh entity.');
    }

    public function testRefreshNotManaged()
    {
        $this->expectException(OGMInvalidArgumentException::class);
        $user = new User('neo', 33);
        $this->em->refresh($user);
    }
}
