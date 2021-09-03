<?php

/*
 * This file is part of the GraphAware Neo4j PHP OGM package.
 *
 * (c) GraphAware Ltd <info@graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphAware\Neo4j\OGM;

use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Relationship;
use GraphAware\Neo4j\OGM\Exception\OGMInvalidArgumentException;
use GraphAware\Neo4j\OGM\Metadata\NodeEntityMetadata;
use GraphAware\Neo4j\OGM\Metadata\RelationshipEntityMetadata;
use GraphAware\Neo4j\OGM\Metadata\RelationshipMetadata;
use GraphAware\Neo4j\OGM\Persister\EntityPersister;
use GraphAware\Neo4j\OGM\Persister\RelationshipEntityPersister;
use GraphAware\Neo4j\OGM\Persister\RelationshipPersister;
use GraphAware\Neo4j\OGM\Proxy\LazyCollection;
use LogicException;
use ReflectionClass;
use ReflectionObject;
use RuntimeException;

class UnitOfWork
{
    private const STATE_NEW = 'STATE_NEW';

    private const STATE_MANAGED = 'STATE_MANAGED';

    private const STATE_DELETED = 'STATE_DELETED';

    private const STATE_DETACHED = 'STATE_DETACHED';

    private array $entityStates = [];

    private array $entityIds = [];

    private array $nodesScheduledForCreate = [];

    private array $nodesScheduledForUpdate = [];

    private array $nodesScheduledForDelete = [];

    private array $nodesSchduledForDetachDelete = [];

    private array $relationshipsScheduledForCreated = [];

    private array $relationshipsScheduledForDelete = [];

    private array $relEntitiesScheduledForCreate = [];

    private array $relEntitesScheduledForUpdate = [];

    private array $relEntitesScheduledForDelete = [];

    private array $persisters = [];

    private array $relationshipEntityPersisters = [];

    private RelationshipPersister $relationshipPersister;

    private array $entitiesById = [];

    private array $managedRelationshipReferences = [];

    private array $entityStateReferences = [];

    private array $managedRelationshipEntities = [];

    private array $relationshipEntityReferences = [];

    private array $relationshipEntityStates = [];

    private array $reEntityIds = [];

    private array $reEntitiesById = [];

    private array $managedRelationshipEntitiesMap = [];

    private array $reOriginalData = [];

    public function __construct(private EntityManager $entityManager)
    {
        $this->relationshipPersister = new RelationshipPersister();
    }

    public function persist($entity)
    {
        if (!$this->isNodeEntity($entity)) {
            return;
        }
        $visited = [];

        $this->doPersist($entity, $visited);
    }

    public function doPersist($entity, array &$visited)
    {
        $oid = spl_object_hash($entity);

        if (isset($visited[$oid])) {
            return;
        }

        $visited[$oid] = $entity;
        $entityState = $this->getEntityState($entity, self::STATE_NEW);

        switch ($entityState) {
            case self::STATE_MANAGED:
                //$this->nodesScheduledForUpdate[$oid] = $entity;
                break;
            case self::STATE_NEW:
                $this->nodesScheduledForCreate[$oid] = $entity;
                break;
            case self::STATE_DELETED:
                throw new LogicException('Node has been deleted');
        }

        $this->cascadePersist($entity, $visited);
        $this->traverseRelationshipEntities($entity, $visited);
    }

    public function cascadePersist($entity, array &$visited)
    {
        $classMetadata = $this->entityManager->getClassMetadataFor(get_class($entity));
        $associations = $classMetadata->getSimpleRelationships();

        foreach ($associations as $association) {
            $value = $association->getValue($entity);
            if ($value instanceof LazyCollection) {
                $value = $value->getAddWithoutFetch();
            }
            if (is_array($value) || $value instanceof Collection) {
                foreach ($value as $assoc) {
                    $this->persistRelationship($entity, $assoc, $association, $visited);
                }
            } else {
                $entityB = $association->getValue($entity);
                if (is_object($entityB)) {
                    $this->persistRelationship($entity, $entityB, $association, $visited);
                }
            }
        }
    }

    public function persistRelationship($entityA, $entityB, RelationshipMetadata $relationship, array &$visited)
    {
        if ($entityB instanceof Collection) {
            foreach ($entityB as $e) {
                $aMeta = $this->entityManager->getClassMetadataFor(get_class($entityA));
                $bMeta = $this->entityManager->getClassMetadataFor(get_class($entityB));
                $type = $relationship->isRelationshipEntity()
                    ? $this->entityManager->getRelationshipEntityMetadata($relationship->getRelationshipEntityClass())
                        ->getType()
                    : $relationship->getType();
                $hashStr = $aMeta->getIdValue($entityA) . $bMeta->getIdValue($entityB) . $type . $relationship->getDirection();
                $hash = md5($hashStr);
                if (!array_key_exists($hash, $this->relationshipsScheduledForCreated)) {
                    $this->relationshipsScheduledForCreated[] = [$entityA, $relationship, $e, $relationship->getPropertyName()];
                }
                $this->doPersist($e, $visited);
            }

            return;
        }
        $this->doPersist($entityB, $visited);
        $this->relationshipsScheduledForCreated[] = [$entityA, $relationship, $entityB, $relationship->getPropertyName()];
    }

    public function flush()
    {
        // Detect changes
        $this->detectRelationshipReferenceChanges();
        $this->detectRelationshipEntityChanges();
        $this->computeRelationshipEntityPropertiesChanges();
        $this->detectEntityChanges();

        // Apply changes
        $this->createNodes();
        $this->createRelationships();
        $this->deleteRelationship();
        $this->createRelationshipEntities();
        $this->updateRelationshipEntities();
        $this->deleteRelationshipEntities();
        $this->updateNodes();
        $this->deleteNodes();

        // Clear changes
        $this->nodesScheduledForCreate
            = $this->nodesScheduledForUpdate
            = $this->nodesScheduledForDelete
            = $this->nodesSchduledForDetachDelete
            = $this->relationshipsScheduledForCreated
            = $this->relationshipsScheduledForDelete
            = $this->relEntitesScheduledForUpdate
            = $this->relEntitiesScheduledForCreate
            = $this->relEntitesScheduledForDelete
            = [];
    }

    public function detectEntityChanges()
    {
        $managed = [];
        foreach ($this->entityStates as $oid => $state) {
            if ($state === self::STATE_MANAGED) {
                $managed[] = $oid;
            }
        }

        foreach ($managed as $oid) {
            $id = $this->entityIds[$oid];
            $entityA = $this->entitiesById[$id];
            $visited = [];
            $this->doPersist($entityA, $visited);
            $entityB = $this->entityStateReferences[$id];
            $this->computeChanges($entityA, $entityB);
        }
    }

    public function addManagedRelationshipReference($entityA, $entityB, $field, RelationshipMetadata $relationship)
    {
        $aoid = spl_object_hash($entityA);
        $boid = spl_object_hash($entityB);
        $this->managedRelationshipReferences[$aoid][$field][] = [
            'entity' => $aoid,
            'target' => $boid,
            'rel' => $relationship,
        ];
        $this->addManaged($entityA);
        $this->addManaged($entityB);
    }

    public function detectRelationshipEntityChanges()
    {
        $managed = [];
        foreach ($this->relationshipEntityStates as $oid => $state) {
            if ($state === self::STATE_MANAGED) {
                $managed[] = $oid;
            }
        }

        foreach ($managed as $oid) {
            $reA = $this->reEntitiesById[$this->reEntityIds[$oid]];
            $reB = $this->relationshipEntityReferences[$this->reEntityIds[$oid]];
            $this->computeRelationshipEntityChanges($reA, $reB);
        }
    }

    public function addManagedRelationshipEntity($entity, $pointOfView, $field)
    {
        $id = $this->entityManager->getRelationshipEntityMetadata(get_class($entity))->getIdValue($entity);
        $oid = spl_object_hash($entity);
        $this->relationshipEntityStates[$oid] = self::STATE_MANAGED;
        $ref = clone $entity;
        $this->reEntitiesById[$id] = $entity;
        $this->reEntityIds[$oid] = $id;
        $this->relationshipEntityReferences[$id] = $ref;
        $poid = spl_object_hash($pointOfView);
        $this->managedRelationshipEntities[$poid][$field][] = $oid;
        $this->managedRelationshipEntitiesMap[$oid][$poid] = $field;
        $this->reOriginalData[$oid] = $this->getOriginalRelationshipEntityData($entity);
    }

    public function getRelationshipEntityById($id)
    {
        if (array_key_exists($id, $this->reEntitiesById)) {
            return $this->reEntitiesById[$id];
        }

        return null;
    }

    public function detectRelationshipReferenceChanges(): void
    {
        foreach ($this->managedRelationshipReferences as $oid => $reference) {
            $entity = $this->entitiesById[$this->entityIds[$oid]];
            foreach ($reference as $info) {
                /** @var RelationshipMetadata $relMeta */
                $relMeta = $info[0]['rel'];
                $value = $relMeta->getValue($entity);
                if ($value instanceof ArrayCollection || $value instanceof AbstractLazyCollection) {
                    $value = $value->toArray();
                }
                if (is_array($value)) {
                    $currentValue = array_map(function ($ref) {
                        return $this->entitiesById[$this->entityIds[$ref['target']]];
                    }, $info);

                    $compare = function ($a, $b) {
                        if ($a === $b) {
                            return 0;
                        }

                        return $a < $b ? -1 : 1;
                    };

                    $added = array_udiff($value, $currentValue, $compare);
                    $removed = array_udiff($currentValue, $value, $compare);

                    foreach ($added as $add) {
                        // Since this is the same property, it should be ok to re-use the first relationship
                        $this->scheduleRelationshipReferenceForCreate($entity, $add, $info[0]['rel']);
                    }
                    foreach ($removed as $remove) {
                        $this->scheduleRelationshipReferenceForDelete($entity, $remove, $info[0]['rel']);
                    }
                } elseif (is_object($value)) {
                    $target = $this->entitiesById[$this->entityIds[$info[0]['target']]];
                    if ($value !== $target) {
                        $this->scheduleRelationshipReferenceForDelete($entity, $target, $info[0]['rel']);
                        $this->scheduleRelationshipReferenceForCreate($entity, $value, $info[0]['rel']);
                    }
                } elseif ($value === null) {
                    foreach ($info as $ref) {
                        $target = $this->entitiesById[$this->entityIds[$ref['target']]];
                        $this->scheduleRelationshipReferenceForDelete($entity, $target, $ref['rel']);
                    }
                }
            }
        }
    }

    public function scheduleRelationshipReferenceForCreate($entity, $target, RelationshipMetadata $relationship)
    {
        $this->relationshipsScheduledForCreated[] = [$entity, $relationship, $target, $relationship->getPropertyName()];
    }

    public function scheduleRelationshipReferenceForDelete($entity, $target, RelationshipMetadata $relationship)
    {
        $this->relationshipsScheduledForDelete[] = [$entity, $relationship, $target, $relationship->getPropertyName()];
    }

    public function traverseRelationshipEntities($entity, array &$visited = [])
    {
        $classMetadata = $this->entityManager->getClassMetadataFor(get_class($entity));
        foreach ($classMetadata->getRelationshipEntities() as $relationshipMetadata) {
            $value = $relationshipMetadata->getValue($entity);
            $notInitialized = $value instanceof AbstractLazyCollection && !$value->isInitialized();
            if (null === $value || ($relationshipMetadata->isCollection() && count($value) === 0) || $notInitialized) {
                continue;
            }
            if ($relationshipMetadata->isCollection()) {
                foreach ($value as $v) {
                    $this->persistRelationshipEntity($v, get_class($entity));
                    $rem = $this->entityManager->getRelationshipEntityMetadata(get_class($v));
                    $toPersistProperty = $rem->getStartNode() === $classMetadata->getClassName() ? $rem->getEndNodeValue($v) : $rem->getStartNodeValue($v);
                    $this->doPersist($toPersistProperty, $visited);
                }
            } else {
                $this->persistRelationshipEntity($value, get_class($entity));
                $rem = $this->entityManager->getRelationshipEntityMetadata(get_class($value));
                $toPersistProperty = $rem->getStartNode() === $classMetadata->getClassName() ? $rem->getEndNodeValue($value) : $rem->getStartNodeValue($value);
                $this->doPersist($toPersistProperty, $visited);
            }
        }
    }

    public function persistRelationshipEntity($entity, $pov)
    {
        $oid = spl_object_hash($entity);

        if (!array_key_exists($oid, $this->relationshipEntityStates)) {
            $this->relEntitiesScheduledForCreate[$oid] = [$entity, $pov];
            $this->relationshipEntityStates[$oid] = self::STATE_NEW;
        }
    }

    public function getEntityState($entity, $assumedState = null)
    {
        $oid = spl_object_hash($entity);

        if (isset($this->entityStates[$oid])) {
            return $this->entityStates[$oid];
        }

        if (null !== $assumedState) {
            return $assumedState;
        }

        $id = $this->entityManager->getClassMetadataFor(get_class($entity))->getIdValue($entity);

        if (!$id) {
            return self::STATE_NEW;
        }

        return self::STATE_DETACHED;
    }

    public function addManaged($entity)
    {
        $oid = spl_object_hash($entity);
        $classMetadata = $this->entityManager->getClassMetadataFor(get_class($entity));
        $id = $classMetadata->getIdValue($entity);
        if (null === $id) {
            throw new LogicException('Entity marked for managed but could not find identity');
        }
        $this->entityStates[$oid] = self::STATE_MANAGED;
        $this->entityIds[$oid] = $id;
        $this->entitiesById[$id] = $entity;
        $this->manageEntityReference($oid);
    }

    public function isManaged(object $entity): bool
    {
        return isset($this->entityIds[spl_object_hash($entity)]);
    }

    public function scheduleDelete($entity, $detachRelationships = false)
    {
        if ($this->isNodeEntity($entity)) {
            $this->nodesScheduledForDelete[] = $entity;
            if ($detachRelationships) {
                $this->nodesSchduledForDetachDelete[] = spl_object_hash($entity);
            }

            return;
        }

        if ($this->isRelationshipEntity($entity)) {
            $this->relEntitesScheduledForDelete[] = $entity;

            return;
        }

        throw new RuntimeException('Neither Node entity or Relationship entity detected');
    }

    public function getEntityById(int $id): ?object
    {
        return $this->entitiesById[$id] ?? null;
    }

    public function getPersister(string $class): EntityPersister
    {
        if (!array_key_exists($class, $this->persisters)) {
            $classMetadata = $this->entityManager->getClassMetadataFor($class);
            $this->persisters[$class] = new EntityPersister($this->entityManager, $class, $classMetadata);
        }

        return $this->persisters[$class];
    }

    public function getRelationshipEntityPersister($class): RelationshipEntityPersister
    {
        if (!array_key_exists($class, $this->relationshipEntityPersisters)) {
            $classMetadata = $this->entityManager->getRelationshipEntityMetadata($class);
            $this->relationshipEntityPersisters[$class] = new RelationshipEntityPersister($this->entityManager, $class, $classMetadata);
        }

        return $this->relationshipEntityPersisters[$class];
    }

    public function hydrateGraphId($oid, $gid)
    {
        $refl0 = new ReflectionObject($this->nodesScheduledForCreate[$oid]);
        $p = $refl0->getProperty('id');
        $p->setAccessible(true);
        $p->setValue($this->nodesScheduledForCreate[$oid], $gid);
    }

    public function hydrateRelationshipEntityId($oid, $gid)
    {
        $refl0 = new ReflectionObject($this->relEntitiesScheduledForCreate[$oid][0]);
        $p = $refl0->getProperty('id');
        $p->setAccessible(true);
        $p->setValue($this->relEntitiesScheduledForCreate[$oid][0], $gid);
        $this->reEntityIds[$oid] = $gid;
        $this->reEntitiesById[$gid] = $this->relEntitiesScheduledForCreate[$oid][0];
        $this->relationshipEntityReferences[$gid] = clone $this->relEntitiesScheduledForCreate[$oid][0];
        $this->reOriginalData[$oid] = $this->getOriginalRelationshipEntityData($this->relEntitiesScheduledForCreate[$oid][0]);
    }

    /**
     * Merges the state of the given detached entity into this UnitOfWork.
     *
     * @param object $entity
     *
     * @return object The managed copy of the entity
     */
    public function merge(object $entity)
    {
        // TODO write me
        trigger_error('Function not implemented.', E_USER_ERROR);
    }

    /**
     * Detaches an entity from the persistence management. It's persistence will
     * no longer be managed by Doctrine.
     *
     * @param object $entity The entity to detach
     */
    public function detach(object $entity)
    {
        $visited = [];

        $this->doDetach($entity, $visited);
    }

    /**
     * Refreshes the state of the given entity from the database, overwriting
     * any local, unpersisted changes.
     *
     * @param object $entity The entity to refresh
     */
    public function refresh(object $entity)
    {
        $visited = [];

        $this->doRefresh($entity, $visited);
    }

    /**
     * Helper method to initialize a lazy loading proxy or persistent collection.
     *
     * @param object $obj
     */
    public function initializeObject(object $obj)
    {
        // TODO write me
        trigger_error('Function not implemented.', E_USER_ERROR);
    }

    public function getNodesScheduledForCreate(): array
    {
        return $this->nodesScheduledForCreate;
    }

    public function isScheduledForCreate(object $entity): bool
    {
        return isset($this->nodesScheduledForCreate[spl_object_hash($entity)]);
    }

    public function getNodesScheduledForUpdate(): array
    {
        return $this->nodesScheduledForUpdate;
    }

    public function getNodesScheduledForDelete(): array
    {
        return $this->nodesScheduledForDelete;
    }

    public function isScheduledForDelete(object $entity): bool
    {
        return isset($this->nodesScheduledForDelete[spl_object_hash($entity)]);
    }

    public function getRelationshipsScheduledForCreated(): array
    {
        return $this->relationshipsScheduledForCreated;
    }

    public function getRelationshipsScheduledForDelete(): array
    {
        return $this->relationshipsScheduledForDelete;
    }

    public function getRelEntitiesScheduledForCreate(): array
    {
        return $this->relEntitiesScheduledForCreate;
    }

    public function getRelEntitesScheduledForUpdate(): array
    {
        return $this->relEntitesScheduledForUpdate;
    }

    public function getRelEntitesScheduledForDelete(): array
    {
        return $this->relEntitesScheduledForDelete;
    }

    /**
     * Get the original state of an entity when it was loaded from the database.
     *
     * @param int $id
     *
     * @return object|null
     */
    public function getOriginalEntityState(int $id): ?object
    {
        if (isset($this->entityStateReferences[$id])) {
            return $this->entityStateReferences[$id];
        }

        return null;
    }

    public function createEntity(Node $node, $className, $id)
    {
        /** todo receive a data of object instead of node object */
        $classMetadata = $this->entityManager->getClassMetadataFor($className);
        $entity = $this->newInstance($classMetadata, $node);
        $classMetadata->setId($entity, $id);
        $this->addManaged($entity);

        return $entity;
    }

    public function createRelationshipEntity(Relationship $relationship, $className, $sourceEntity, $field): object
    {
        $classMetadata = $this->entityManager->getClassMetadataFor($className);
        $o = $classMetadata->newInstance();
        $classMetadata->setId($o, $relationship->getId());
        $this->addManagedRelationshipEntity($o, $sourceEntity, $field);

        return $o;
    }

    private function manageEntityReference($oid): void
    {
        $id = $this->entityIds[$oid];
        $entity = $this->entitiesById[$id];
        $this->entityStateReferences[$id] = clone $entity;
    }

    private function computeChanges($entityA, $entityB): void
    {
        $classMetadata = $this->entityManager->getClassMetadataFor(get_class($entityA));
        $propertyFields = array_merge($classMetadata->getPropertiesMetadata(), $classMetadata->getLabeledProperties());
        foreach ($propertyFields as $field => $meta) {
            // force proxy to initialize (only needed with proxy manager 1.x
            $reflClass = new ReflectionClass($classMetadata->getClassName());
            foreach ($reflClass->getMethods() as $method) {
                if ($method->getNumberOfRequiredParameters() === 0 && $method->getName() === 'getId') {
                    $entityA->getId();
                }
            }
            $p1 = $meta->getValue($entityA);
            $p2 = $meta->getValue($entityB);
            if ($p1 !== $p2) {
                $this->nodesScheduledForUpdate[spl_object_hash($entityA)] = $entityA;
            }
        }
    }

    private function computeRelationshipEntityPropertiesChanges(): void
    {
        foreach ($this->relationshipEntityStates as $oid => $state) {
            if ($state === self::STATE_MANAGED) {
                $e = $this->reEntitiesById[$this->reEntityIds[$oid]];
                $cm = $this->entityManager->getClassMetadataFor(get_class($e));
                $newValues = $cm->getPropertyValuesArray($e);
                $originalValues = $this->reOriginalData[$oid];
                if (count(array_diff($originalValues, $newValues)) > 0) {
                    $this->relEntitesScheduledForUpdate[$oid] = $e;
                }
            }
        }
    }

    private function computeRelationshipEntityChanges($entityA, $entityB): void
    {
        $classMetadata = $this->entityManager->getRelationshipEntityMetadata(get_class($entityA));
        foreach ($classMetadata->getPropertiesMetadata() as $meta) {
            if ($meta->getValue($entityA) !== $meta->getValue($entityB)) {
                $this->relEntitesScheduledForUpdate[spl_object_hash($entityA)] = $entityA;
            }
        }
    }

    private function getOriginalRelationshipEntityData($entity): array
    {
        $classMetadata = $this->entityManager->getClassMetadataFor(get_class($entity));

        return $classMetadata->getPropertyValuesArray($entity);
    }

    private function removeManaged($entity): void
    {
        $oid = spl_object_hash($entity);
        unset($this->entityIds[$oid]);

        $classMetadata = $this->entityManager->getClassMetadataFor(get_class($entity));
        $id = $classMetadata->getIdValue($entity);
        if (null === $id) {
            throw new LogicException('Entity marked as not managed but could not find identity');
        }
        unset($this->entitiesById[$id]);
    }

    /**
     * Executes a detach operation on the given entity.
     *
     * @param object $entity
     * @param array $visited
     */
    private function doDetach(object $entity, array &$visited): void
    {
        $oid = spl_object_hash($entity);

        if (isset($visited[$oid])) {
            return; // Prevent infinite recursion
        }

        $visited[$oid] = $entity; // mark visited

        switch ($this->getEntityState($entity, self::STATE_DETACHED)) {
            case self::STATE_MANAGED:
                if ($this->isManaged($entity)) {
                    $this->removeManaged($entity);
                }

                unset(
                    $this->nodesScheduledForCreate[$oid],
                    $this->nodesScheduledForUpdate[$oid],
                    $this->nodesScheduledForDelete[$oid],
                    $this->entityStates[$oid]
                );
                break;
            case self::STATE_NEW:
            case self::STATE_DETACHED:
                return;
        }

        $this->entityStates[$oid] = self::STATE_DETACHED;
    }

    /**
     * Cascades a detach operation to associated entities.
     *
     * @param object $entity The entity to refresh
     * @param array $visited The already visited entities during cascades
     */
    private function cascadeDetach(object $entity, array &$visited): void
    {
        $class = $this->entityManager->getClassMetadata(get_class($entity));

        foreach ($class->getRelationships() as $relationship) {
            $value = $relationship->getValue($entity);

            switch (true) {
                case $value instanceof Collection:
                case is_array($value):
                    foreach ($value as $relatedEntity) {
                        $this->doDetach($relatedEntity, $visited);
                    }
                    break;
                case $value !== null:
                    $this->doDetach($value, $visited);
                    break;
                default:
            }
        }
    }

    /**
     * Executes a refresh operation on an entity.
     *
     * @param object $entity The entity to refresh
     * @param array $visited The already visited entities during cascades
     */
    private function doRefresh(object $entity, array &$visited): void
    {
        $oid = spl_object_hash($entity);

        if (isset($visited[$oid])) {
            return; // Prevent infinite recursion
        }

        $visited[$oid] = $entity; // mark visited

        if ($this->getEntityState($entity) !== self::STATE_MANAGED) {
            throw OGMInvalidArgumentException::entityNotManaged($entity);
        }

        $this->getPersister(get_class($entity))->refresh($this->entityIds[$oid], $entity);

        $this->cascadeRefresh($entity, $visited);
    }

    /**
     * Cascades a refresh operation to associated entities.
     *
     * @param object $entity The entity to refresh
     * @param array $visited The already visited entities during cascades
     */
    private function cascadeRefresh(object $entity, array &$visited): void
    {
        $class = $this->entityManager->getClassMetadata(get_class($entity));

        foreach ($class->getRelationships() as $relationship) {
            $value = $relationship->getValue($entity);

            switch (true) {
                case $value instanceof Collection:
                case is_array($value):
                    foreach ($value as $relatedEntity) {
                        $this->doRefresh($relatedEntity, $visited);
                    }
                    break;
                case $value !== null:
                    $this->doRefresh($value, $visited);
                    break;
                default:
            }
        }
    }

    private function newInstance(NodeEntityMetadata $class, Node $node)
    {
        $proxyFactory = $this->entityManager->getProxyFactory($class);
        /* @todo make possible to instantiate proxy without the node object */
        return $proxyFactory->fromNode($node);
    }

    private function isNodeEntity($entity): bool
    {
        $meta = $this->entityManager->getClassMetadataFor(get_class($entity));

        return $meta instanceof NodeEntityMetadata;
    }

    private function isRelationshipEntity($entity): bool
    {
        $meta = $this->entityManager->getClassMetadataFor($entity::class);

        return $meta instanceof RelationshipEntityMetadata;
    }

    private function createNodes(): void
    {
        foreach ($this->nodesScheduledForCreate as $nodeToCreate) {
            $oid = spl_object_hash($nodeToCreate);
            $this->traverseRelationshipEntities($nodeToCreate);
            $persister = $this->getPersister($nodeToCreate::class);
            $result = $this->entityManager->getDatabaseDriver()->runStatement($persister->getCreateQuery($nodeToCreate));
            foreach ($result->toArray() as $record) {
                $gid = $record->get('id');
                $this->hydrateGraphId($oid, $gid);
                $this->entitiesById[$gid] = $this->nodesScheduledForCreate[$oid];
                $this->entityIds[$oid] = $gid;
                $this->entityStates[$oid] = self::STATE_MANAGED;
                $this->manageEntityReference($oid);
            }
        }
    }

    private function createRelationships(): void
    {
        $statements = [];
        foreach ($this->relationshipsScheduledForCreated as $relationship) {
            $aoid = spl_object_hash($relationship[0]);
            $boid = spl_object_hash($relationship[2]);
            $field = $relationship[3];
            $this->managedRelationshipReferences[$aoid][$field][] = [
                'entity' => $aoid,
                'target' => $boid,
                'rel' => $relationship[1],
            ];

            $statement = $this->relationshipPersister->getRelationshipQuery(
                $this->entityIds[spl_object_hash($relationship[0])],
                $relationship[1],
                $this->entityIds[spl_object_hash($relationship[2])]
            );
            $statements[] = $statement;
        }
        $this->entityManager->getDatabaseDriver()->runStatements($statements);
    }

    private function updateNodes(): void
    {
        $statements = [];
        foreach ($this->nodesScheduledForUpdate as $entity) {
            $this->traverseRelationshipEntities($entity);
            $statements[] = $this->getPersister(get_class($entity))->getUpdateQuery($entity);
        }
        $this->entityManager->getDatabaseDriver()->runStatements($statements);
    }

    private function deleteNodes(): void
    {
        $possiblyDeleted = [];
        $statements = [];
        foreach ($this->nodesScheduledForDelete as $entity) {
            if (in_array(spl_object_hash($entity), $this->nodesSchduledForDetachDelete)) {
                $statements[] = $this->getPersister(get_class($entity))->getDetachDeleteQuery($entity);
            } else {
                $statements[] = $this->getPersister(get_class($entity))->getDeleteQuery($entity);
            }
            $possiblyDeleted[] = spl_object_hash($entity);
        }
        $tsx = $this->entityManager->getDatabaseDriver()->beginTransaction($statements);
        $tsx->commit();

        foreach ($possiblyDeleted as $oid) {
            $this->entityStates[$oid] = self::STATE_DELETED;
        }
    }

    private function deleteRelationship(): void
    {
        $statements = [];
        if (count($this->relationshipsScheduledForDelete) > 0) {
            foreach ($this->relationshipsScheduledForDelete as $relationship) {
                $statements [] = $this->relationshipPersister->getDeleteRelationshipQuery(
                    $this->entityIds[spl_object_hash($relationship[0])],
                    $this->entityIds[spl_object_hash($relationship[2])],
                    $relationship[1]
                );
            }
            $this->entityManager->getDatabaseDriver()->runStatements($statements);
        }
    }

    private function createRelationshipEntities(): void
    {
        foreach ($this->relEntitiesScheduledForCreate as $info) {
            $rePersister = $this->getRelationshipEntityPersister(get_class($info[0]));
            $statement = $rePersister->getCreateQuery($info[0], $info[1]);
            $result = $this->entityManager->getDatabaseDriver()->runStatement($statement);
            $this->setRelationshipEntityStates($result->toArray());
        }
    }

    private function updateRelationshipEntities(): void
    {
        foreach ($this->relEntitesScheduledForUpdate as $entity) {
            $rePersister = $this->getRelationshipEntityPersister(get_class($entity));
            $statement = $rePersister->getUpdateQuery($entity);
            $result = $this->entityManager->getDatabaseDriver()->runStatement($statement);
            $this->setRelationshipEntityStates($result->toArray());
        }
    }

    private function deleteRelationshipEntities(): void
    {
        foreach ($this->relEntitesScheduledForDelete as $o) {
            $statement = $this->getRelationshipEntityPersister(get_class($o))->getDeleteQuery($o);
            $result = $this->entityManager->getDatabaseDriver()->runStatement($statement);
            foreach ($result->toArray() as $record) {
                $oid = $record->get('oid');
                $this->relationshipEntityStates[$record->get('oid')] = self::STATE_DELETED;
                $id = $this->reEntityIds[$oid];
                unset($this->reEntityIds[$oid], $this->reEntitiesById[$id]);
            }
        }
    }

    private function setRelationshipEntityStates(array $entities): void
    {
        foreach ($entities as $record) {
            $gid = $record->get('id');
            $oid = $record->get('oid');
            $this->hydrateRelationshipEntityId($oid, $gid);
            $this->relationshipEntityStates[$oid] = self::STATE_MANAGED;
        }
    }
}
