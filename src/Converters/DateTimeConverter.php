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

use DateTime;
use DateTimeZone;
use Exception;
use GraphAware\Neo4j\OGM\Exception\ConverterException;

class DateTimeConverter extends Converter
{
    private const DEFAULT_FORMAT = 'timestamp';

    private const LONG_TIMESTAMP_FORMAT = 'long_timestamp';

    public function getName(): string
    {
        return 'datetime';
    }

    public function toDatabaseValue($value, array $options): float|int|string|null
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof DateTime) {
            $format = $options['format'] ?? self::DEFAULT_FORMAT;

            if (self::DEFAULT_FORMAT === $format) {
                return $value->getTimestamp();
            }

            if (self::LONG_TIMESTAMP_FORMAT === $format) {
                return $value->getTimestamp() * 1000;
            }

            try {
                return $value->format($format);
            } catch (Exception $e) {
                throw new ConverterException(sprintf('Error while converting timestamp: %s', $e->getMessage()));
            }
        }

        throw new ConverterException(sprintf('Unable to convert value in converter "%s"', $this->getName()));
    }

    public function toPHPValue(array $values, array $options): DateTime|false|null
    {
        if (!isset($values[$this->propertyName])) {
            return null;
        }

        $tz = isset($options['timezone'])
            ? new DateTimeZone($options['timezone'])
            : new DateTimeZone(date_default_timezone_get());

        $format = $options['format'] ?? self::DEFAULT_FORMAT;
        $v = $values[$this->propertyName];

        if (self::DEFAULT_FORMAT === $format) {
            return DateTime::createFromFormat('U', $v, $tz);
        }

        if (self::LONG_TIMESTAMP_FORMAT === $format) {
            return DateTime::createFromFormat('U', (string)round($v / 1000), $tz);
        }

        return DateTime::createFromFormat($format, $v);
    }
}
