<?php

namespace GraphAware\Neo4j\OGM\Tests\Community\Issue103;

use Doctrine\Common\Collections\ArrayCollection;
use GraphAware\Neo4j\OGM\Annotations as OGM;

/**
 * @OGM\Node(label="Entity")
 */
class Entity
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
     * @OGM\Relationship(type="HAS_CONTEXT", direction="OUTGOING", targetEntity="Context", collection=true, mappedBy="entity")
     * @var ArrayCollection|Context[];
     */
    protected array|ArrayCollection $contexts;

    public function __construct($name)
    {
        $this->name = $name;

        $this->contexts = new ArrayCollection();
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

    /**
     * @return ArrayCollection|Context[];
     */
    public function getContexts(): ArrayCollection|array
    {
        return $this->contexts;
    }

    /**
     * @param Context $context
     */
    public function addContext(Context $context): void
    {
        if (!$this->contexts->contains($context)) {
            $this->contexts->add($context);
        }
    }

    /**
     * @param Context $context
     */
    public function removeContext(Context $context): void
    {
        if ($this->contexts->contains($context)) {
            $this->contexts->removeElement($context);
        }
    }
}
