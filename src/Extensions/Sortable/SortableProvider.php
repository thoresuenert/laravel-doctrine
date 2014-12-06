<?php namespace Mitch\LaravelDoctrine\Extensions\Sortable;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\EventManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Mitch\LaravelDoctrine\Extensions\DoctrineExtension;
use Gedmo;
use Mitch\LaravelDoctrine\Extensions\ExtendedMappingDriverChain;
use Mitch\LaravelDoctrine\Extensions\Sortable\Mapping\Driver\SortableDriver;

class SortableProvider implements DoctrineExtension{

    public static function loadExtension(Configuration $metadata, $config, ExtendedMappingDriverChain $driverChain, CachedReader $cachedAnnotationReader, EntityManager $entityManager, EventManager $eventManager)
    {
        AnnotationRegistry::registerFile(__DIR__.'/Mapping/Annotation/Sortable.php');

        // registerSortabledriver;
        self::registerSortableDriver($cachedAnnotationReader, $driverChain, $config);

        $eventManager->addEventSubscriber(new SortableEventSubscriber());


    }

    private static function registerSortableDriver($cachedAnnotationReader, $driverChain, $config)
    {
        $sortableDriver = new SortableDriver(
            $cachedAnnotationReader, // our cached annotation reader
            (array) $config['metadata'] // paths to look in
        );
        $driverChain->addDriver($sortableDriver);
    }


}