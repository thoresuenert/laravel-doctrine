<?php  namespace Mitch\LaravelDoctrine\Extensions;


use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\EventManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;

interface DoctrineExtension {

    public static function loadExtension(Configuration $metadata, $config, ExtendedMappingDriverChain $driverChain, CachedReader $cachedAnnotationReader, EntityManager $entityManager, EventManager $eventManager);

}