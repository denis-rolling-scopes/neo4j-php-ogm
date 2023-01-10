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

namespace GraphAware\Neo4j\OGM\Exception;

/**
 * Contains exception messages for all invalid lifecycle state exceptions inside UnitOfWork.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @credit Benjamin Eberlei <kontakt@beberlei.de>
 */
class OGMInvalidArgumentException extends \InvalidArgumentException
{
    public static function entityNotManaged(object $entity): OGMInvalidArgumentException
    {
        return new self('Entity ' . self::objectToString($entity) . ' is not managed. An entity is managed if ' .
            'its fetched from the database or registered as new through EntityManager#persist');
    }

    private static function objectToString(object $obj): string
    {
        return method_exists($obj, '__toString') ? (string) $obj : $obj::class . '@' . spl_object_hash($obj);
    }
}
