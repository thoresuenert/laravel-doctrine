<?php namespace Mitch\LaravelDoctrine\Extensions;


use Doctrine\ORM\Mapping\ClassMetadataFactory;
use ReflectionProperty;

class ExtendedClassMetadataFactory extends ClassMetadataFactory
{
    protected function newClassMetadataInstance($className)
    {
        // now this is the only hack required to get it work:
        $reflProperty = new ReflectionProperty('Doctrine\ORM\Mapping\ClassMetadataFactory', 'em');
        $reflProperty->setAccessible(true);
        $em = $reflProperty->getValue($this);
        return new ExtendedClassMetadata($className, $em->getConfiguration()->getNamingStrategy());
    }
}