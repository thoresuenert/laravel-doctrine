<?php namespace Mitch\LaravelDoctrine\Extensions\SoftDeleteable;

use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\EventManager;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Mitch\LaravelDoctrine\Extensions\DoctrineExtension;
use Mitch\LaravelDoctrine\Extensions\ExtendedMappingDriverChain;

class SoftDeleteableProvider implements DoctrineExtension {


    public static function loadExtension(Configuration $metadata, $config, ExtendedMappingDriverChain $driverChain, CachedReader $cachedAnnotationReader, EntityManager $entityManager, EventManager $eventManager)
    {
        $metadata->addFilter('trashed', TrashedFilter::class);
        $eventManager->addEventListener(Events::onFlush, new SoftDeleteableListener);
        $entityManager->getFilters()->enable('trashed');
    }
}