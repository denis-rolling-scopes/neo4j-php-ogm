<?php

namespace GraphAware\Neo4j\OGM\Tests\Community\Issue103;

use Doctrine\Common\Collections\ArrayCollection;
use GraphAware\Neo4j\OGM\Annotations as OGM;

/**
 * @OGM\Node(label="Context")
 */
class Context
{
    /**
     * @OGM\GraphId()
     * @var int
     */
    protected int $id;

    /**
     * @OGM\Property(type="string")
     * @var string
     */
    protected string $name;

    /**
     * @OGM\Relationship(type="HAS_CONTEXT", direction="INCOMING", targetEntity="Entity", collection=false, mappedBy="contexts")
     * @var Entity
     */
    protected Entity $entity;

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEntity(): Entity
    {
        return $this->entity;
    }

    public function setEntity(Entity $entity): void
    {
        $this->entity = $entity;
    }
}
