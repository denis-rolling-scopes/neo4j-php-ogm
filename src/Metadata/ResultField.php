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

namespace GraphAware\Neo4j\OGM\Metadata;

class ResultField
{
    private const FIELD_TYPE_ENTITY = 'ENTITY';

    protected ?ClassMetadata $targetMetadata;

    public function __construct(protected $fieldName, protected $fieldType, protected $target)
    {
    }

    public function getFieldName(): mixed
    {
        return $this->fieldName;
    }

    public function getFieldType(): mixed
    {
        return $this->fieldType;
    }

    public function getTarget(): mixed
    {
        return $this->target;
    }

    public function isEntity(): bool
    {
        return $this->fieldType === self::FIELD_TYPE_ENTITY;
    }

    public function setMetadata(ClassMetadata $metadata)
    {
        $this->targetMetadata = $metadata;
    }

    public function getTargetMetadata(): ?ClassMetadata
    {
        return $this->targetMetadata;
    }
}
