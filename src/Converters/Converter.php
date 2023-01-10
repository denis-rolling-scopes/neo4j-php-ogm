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

namespace GraphAware\Neo4j\OGM\Converters;

abstract class Converter
{
    private const DATETIME = 'datetime';

    private static array $converterMap = [
        self::DATETIME => DateTimeConverter::class,
    ];

    private static array $converterObjects = [];

    final private function __construct(protected string $propertyName)
    {
    }

    abstract public function getName();

    abstract public function toDatabaseValue($value, array $options);

    abstract public function toPHPValue(array $values, array $options);

    public static function getConverter(string $name, string $propertyName): Converter
    {
        $objectK = $name . $propertyName;
        if (! isset(self::$converterObjects[$objectK])) {
            if (! isset(self::$converterMap[$name])) {
                throw new \InvalidArgumentException(sprintf('No converter named "%s" found', $name));
            }

            self::$converterObjects[$objectK] = new self::$converterMap[$name]($propertyName);
        }

        return self::$converterObjects[$objectK];
    }

    public static function addConverter(string $name, string $class): void
    {
        if (isset(self::$converterMap[$name])) {
            throw new \InvalidArgumentException(sprintf('Converter with name "%s" already exist', $name));
        }

        self::$converterMap[$name] = $class;
    }

    public static function hasConverter(string $name): bool
    {
        return isset(self::$converterMap[$name]);
    }
}
