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

use GraphAware\Neo4j\OGM\Annotations\Label;
use ReflectionProperty;

final class LabeledPropertyMetadata
{
    private string $labelName;

    public function __construct(
        private $propertyName,
        private ReflectionProperty $reflectionProperty,
        Label $annotation
    ) {
        $this->labelName = $annotation->name;
    }

    /**
     * @return string
     */
    public function getPropertyName()
    {
        return $this->propertyName;
    }

    /**
     * @param object $object
     *
     * @return mixed
     */
    public function getValue($object)
    {
        $this->reflectionProperty->setAccessible(true);

        return $this->reflectionProperty->getValue($object);
    }

    /**
     * @return string
     */
    public function getLabelName()
    {
        return $this->labelName;
    }

    /**
     * @param object $object
     * @param bool $value
     */
    public function setLabel(object $object, bool $value)
    {
        $this->reflectionProperty->setAccessible(true);
        $this->reflectionProperty->setValue($object, $value);
    }

    /**
     * @param object $object
     *
     * @return bool
     */
    public function isLabelSet(object $object): bool
    {
        $this->reflectionProperty->setAccessible(true);
        $v = $this->reflectionProperty->getValue($object);
        if (true === $v) {
            return true;
        }

        return false;
    }
}
