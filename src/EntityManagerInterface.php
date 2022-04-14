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

namespace GraphAware\Neo4j\OGM;

use Doctrine\Common\EventManager;
use Doctrine\Persistence\ObjectManager;
use GraphAware\Neo4j\OGM\Hydrator\EntityHydrator;
use GraphAware\Neo4j\OGM\Metadata\NodeEntityMetadata;
use GraphAware\Neo4j\OGM\Metadata\QueryResultMapper;
use GraphAware\Neo4j\OGM\Persisters\EntityPersister;
use GraphAware\Neo4j\OGM\Proxy\ProxyFactory;
use GraphAware\Neo4j\OGM\Repository\BaseRepository;

interface EntityManagerInterface extends ObjectManager
{
    public static function create(
        string $host,
        string $cacheDir = null,
        EventManager $eventManager = null
    ): EntityManagerInterface;

    public function getEventManager(): EventManager;

    public function getUnitOfWork(): UnitOfWork;

    public function getDatabaseDriver();

    public function getResultMappingMetadata(string $class): QueryResultMapper;

    public function getClassMetadataFor($class);

    public function getRelationshipEntityMetadata(string $class);

    public function getRepository($class): BaseRepository;

    public function getProxyDirectory(): string;

    public function getProxyFactory(NodeEntityMetadata $entityMetadata): ProxyFactory;

    public function getEntityHydrator(string $className): EntityHydrator;

    public function getEntityPersister(string $className): EntityPersister;

    public function createQuery(string $cql = ''): Query;

    public function registerPropertyConverter(string $name, string $classname): void;
}
