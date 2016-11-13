<?php

namespace GraphAware\Neo4j\OGM\Exception;

/**
 * Contains exception messages for all invalid lifecycle state exceptions inside UnitOfWork
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @credit Benjamin Eberlei <kontakt@beberlei.de>
 */
class OGMInvalidArgumentException extends \InvalidArgumentException
{
    /**
     * @param object $entity
     *
     * @return OGMInvalidArgumentException
     */
    static public function entityNotManaged($entity)
    {
        return new self("Entity " . self::objToStr($entity) . " is not managed. An entity is managed if its fetched " .
            "from the database or registered as new through EntityManager#persist");
    }

    /**
     * Helper method to show an object as string.
     *
     * @param object $obj
     *
     * @return string
     */
    private static function objToStr($obj)
    {
        return method_exists($obj, '__toString') ? (string)$obj : get_class($obj).'@'.spl_object_hash($obj);
    }
}
