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

use ReflectionProperty;

final class EntityIdMetadata
{
    public function __construct(
        private string $propertyName,
        private ReflectionProperty $reflectionProperty,
    ) {
    }

    public function getValue(object $object): mixed
    {
        $this->reflectionProperty->setAccessible(true);

        return $this->reflectionProperty->getValue($object);
    }

    public function setValue(object $object, string|int $value): void
    {
        $this->reflectionProperty->setAccessible(true);
        $this->reflectionProperty->setValue($object, $value);
    }

    public function getPropertyName(): string
    {
        return $this->propertyName;
    }
}
