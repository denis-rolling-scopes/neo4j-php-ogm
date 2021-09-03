<?php

/*
 * This file is part of the GraphAware Neo4j PHP OGM package.
 *
 * (c) GraphAware Ltd <info@graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphAware\Neo4j\OGM\Repository;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Common\Persistence\ObjectRepository;
use GraphAware\Neo4j\OGM\EntityManager;
use GraphAware\Neo4j\OGM\Metadata\NodeEntityMetadata;

class BaseRepository implements ObjectRepository, Selectable
{
    public function __construct(
        protected NodeEntityMetadata $classMetadata,
        protected EntityManager $entityManager,
        protected string $className
    ) {
    }

    public function find($id): ?object
    {
        return $this->findOneById($id);
    }

    public function findAll(): array
    {
        return $this->findBy([]);
    }

    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null): array
    {
        $persister = $this->entityManager->getEntityPersister($this->className);

        return $persister->loadAll($criteria, $orderBy, $limit, $offset);
    }

    /**
     * @param array      $criteria
     * @param array|null $orderBy
     *
     * @return object|null
     */
    public function findOneBy(array $criteria, array $orderBy = null)
    {
        $persister = $this->entityManager->getEntityPersister($this->className);

        return $persister->load($criteria);
    }

    /**
     * @param int $id
     *
     * @return object|null
     */
    public function findOneById(int $id): ?object
    {
        $persister = $this->entityManager->getEntityPersister($this->className);

        return $persister->loadOneById($id);
    }

    /**
     * @param Criteria $criteria
     *
     * @return array
     */
    public function matching(Criteria $criteria): array
    {
        $clause = [];
        /** @var Comparison $whereClause */
        $whereClause = $criteria->getWhereExpression();
        if (null !== $whereClause) {
            if (Comparison::EQ !== $whereClause->getOperator()) {
                throw new \InvalidArgumentException(sprintf('Support for Selectable is limited to the EQUALS "=" operator,
                 % given', $whereClause->getOperator()));
            }

            $clause = [$whereClause->getField() => $whereClause->getValue()->getValue()];
        }

        return $this->findBy($clause, $criteria->getOrderings(), $criteria->getMaxResults(), $criteria->getFirstResult());
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }
}
