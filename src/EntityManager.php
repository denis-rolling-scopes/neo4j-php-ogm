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

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\FileCacheReader;
use Doctrine\Common\EventManager;
use Doctrine\Persistence\Mapping\ClassMetadata;
use GraphAware\Neo4j\OGM\Converters\Converter;
use GraphAware\Neo4j\OGM\Exception\MappingException;
use GraphAware\Neo4j\OGM\Hydrator\EntityHydrator;
use GraphAware\Neo4j\OGM\Metadata\Factory\Annotation\AnnotationGraphEntityMetadataFactory;
use GraphAware\Neo4j\OGM\Metadata\Factory\GraphEntityMetadataFactoryInterface;
use GraphAware\Neo4j\OGM\Metadata\NodeEntityMetadata;
use GraphAware\Neo4j\OGM\Metadata\QueryResultMapper;
use GraphAware\Neo4j\OGM\Metadata\RelationshipEntityMetadata;
use GraphAware\Neo4j\OGM\Persisters\EntityPersister;
use GraphAware\Neo4j\OGM\Proxy\ProxyFactory;
use GraphAware\Neo4j\OGM\Repository\BaseRepository;
use GraphAware\Neo4j\OGM\Util\ClassUtils;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;

class EntityManager implements EntityManagerInterface
{
    protected ?UnitOfWork $uow;

    protected array $repositories = [];

    protected array $resultMappers = [];

    protected array $loadedMetadata = [];

    protected string $proxyDirectory;

    protected array $proxyFactories = [];

    protected array $entityHydrators = [];

    public function __construct(
        protected ClientInterface $databaseDriver,
        string $cacheDirectory = null,
        protected ?EventManager $eventManager = null,
        protected ?GraphEntityMetadataFactoryInterface $metadataFactory = null,
        protected bool $isV4 = false,
    ) {
        $this->eventManager = $eventManager ?: new EventManager();
        $this->uow = new UnitOfWork($this);

        if ($this->metadataFactory === null) {
            $reader = new FileCacheReader(new AnnotationReader(), $cacheDirectory, true);
            $this->metadataFactory = new AnnotationGraphEntityMetadataFactory($reader);
        }
        $this->proxyDirectory = $cacheDirectory;
    }

    public static function create(
        string $host,
        string $cacheDir = null,
        EventManager $eventManager = null
    ): EntityManagerInterface {
        $cache = $cacheDir ?: sys_get_temp_dir();
        $client = ClientBuilder::create()
            ->withDriver('neo4j', $host)
            ->build();

        return new self($client, $cache, $eventManager, isV4: true);
    }

    /**
     * {@inheritdoc}
     */
    public function find($className, $id): ?object
    {
        return $this->getRepository($className)->findOneById($id);
    }

    /**
     * {@inheritdoc}
     */
    public function remove($object, $detachRelationships = false)
    {
        $this->uow->scheduleDelete($object, $detachRelationships);
    }

    /**
     * {@inheritdoc}
     */
    public function merge($entity): object
    {
        if (!is_object($entity)) {
            throw new \Exception('EntityManager::merge() expects an object');
        }

        return $this->uow->merge($entity);
    }

    /**
     * {@inheritdoc}
     */
    public function detach($entity)
    {
        if (!is_object($entity)) {
            throw new \Exception('EntityManager::detach() expects an object');
        }

        $this->uow->detach($entity);
    }

    /**
     * {@inheritdoc}
     */
    public function refresh($entity)
    {
        if (!is_object($entity)) {
            throw new \Exception('EntityManager::refresh() expects an object');
        }

        $this->uow->refresh($entity);
    }

    public function getClassMetadata($className): ClassMetadata
    {
        if (array_key_exists($className, $this->loadedMetadata)) {
            return $this->loadedMetadata[$className];
        }

        return $this->metadataFactory->create($className);
    }

    public function getMetadataFactory(): GraphEntityMetadataFactoryInterface
    {
        return $this->metadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function initializeObject($obj)
    {
        $this->uow->initializeObject($obj);
    }

    /**
     * {@inheritdoc}
     */
    public function contains($entity): bool
    {
        return $this->uow->isScheduledForCreate($entity)
        || $this->uow->isManaged($entity)
        && !$this->uow->isScheduledForDelete($entity);
    }

    public function getEventManager(): EventManager
    {
        return $this->eventManager;
    }

    public function persist($entity)
    {
        if (!is_object($entity)) {
            throw new \Exception('EntityManager::persist() expects an object');
        }

        $this->uow->persist($entity);
    }

    public function flush()
    {
        $this->uow->flush();
    }

    public function getUnitOfWork(): UnitOfWork
    {
        return $this->uow;
    }

    public function getDatabaseDriver(): ClientInterface
    {
        return $this->databaseDriver;
    }

    public function getResultMappingMetadata(string $class): QueryResultMapper
    {
        if (!array_key_exists($class, $this->resultMappers)) {
            $this->resultMappers[$class] = $this->metadataFactory->createQueryResultMapper($class);
            foreach ($this->resultMappers[$class]->getFields() as $field) {
                if ($field->isEntity()) {
                    $targetFQDN = ClassUtils::getFullClassName($field->getTarget(), $class);
                    $field->setMetadata($this->getClassMetadataFor($targetFQDN));
                }
            }
        }

        return $this->resultMappers[$class];
    }

    public function getClassMetadataFor($class)
    {
        if (!array_key_exists($class, $this->loadedMetadata)) {
            $this->loadedMetadata[$class] = $this->metadataFactory->create($class);
        }

        return $this->loadedMetadata[$class];
    }

    public function getRelationshipEntityMetadata(string $class): RelationshipEntityMetadata
    {
        if (!array_key_exists($class, $this->loadedMetadata)) {
            $metadata = $this->metadataFactory->create($class);
            if (!$metadata instanceof RelationshipEntityMetadata) {
                // $class is not an relationship entity
                throw new MappingException(sprintf('The class "%s" was configured to be an RelationshipEntity but no @OGM\RelationshipEntity class annotation was found', $class));
            }
            $this->loadedMetadata[$class] = $metadata;
        }

        return $this->loadedMetadata[$class];
    }

    public function getRepository($class): BaseRepository
    {
        $classMetadata = $this->getClassMetadataFor($class);
        if (!array_key_exists($class, $this->repositories)) {
            $repositoryClassName = $classMetadata->hasCustomRepository() ? $classMetadata->getRepositoryClass() : BaseRepository::class;
            $this->repositories[$class] = new $repositoryClassName($classMetadata, $this, $class);
        }

        return $this->repositories[$class];
    }

    public function clear($objectName = null)
    {
        $this->uow = null;
        $this->uow = new UnitOfWork($this);
    }

    public function getProxyDirectory(): string
    {
        return $this->proxyDirectory;
    }

    public function getProxyFactory(NodeEntityMetadata $entityMetadata): ProxyFactory
    {
        if (!array_key_exists($entityMetadata->getClassName(), $this->proxyFactories)) {
            $this->proxyFactories[$entityMetadata->getClassName()] = new ProxyFactory($this, $entityMetadata);
        }

        return $this->proxyFactories[$entityMetadata->getClassName()];
    }

    public function getEntityHydrator(string $className): EntityHydrator
    {
        if (!array_key_exists($className, $this->entityHydrators)) {
            $this->entityHydrators[$className] = new EntityHydrator($className, $this);
        }

        return $this->entityHydrators[$className];
    }

    public function getEntityPersister(string $className): EntityPersister
    {
        return new EntityPersister($className, $this->getClassMetadataFor($className), $this);
    }

    public function createQuery(string $cql = ''): Query
    {
        $query = new Query($this);

        if (!empty($cql)) {
            $query->setCQL($cql);
        }

        return $query;
    }

    public function registerPropertyConverter(string $name, string $classname): void
    {
        Converter::addConverter($name, $classname);
    }

    public function isV4(): bool
    {
        return $this->isV4;
    }
}
