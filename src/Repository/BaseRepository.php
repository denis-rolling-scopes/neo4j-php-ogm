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

namespace GraphAware\Neo4j\OGM\Repository;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ObjectRepository;
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

    public function findOneBy(array $criteria, array $orderBy = null): array|object|null
    {
        $persister = $this->entityManager->getEntityPersister($this->className);

        return $persister->load($criteria);
    }

    public function findOneById(int $id): ?object
    {
        $persister = $this->entityManager->getEntityPersister($this->className);

        return $persister->loadOneById($id);
    }

    public function matching(Criteria $criteria): array|Collection
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

    public function getClassName(): string
    {
        return $this->className;
    }
}
