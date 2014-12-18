<?php namespace Mitch\LaravelDoctrine\Extensions\Sortable\Mapping\Driver;

use Doctrine\Common\Persistence\Mapping\Driver\AnnotationDriver;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Mitch\LaravelDoctrine\Extensions\Sortable\Sortable as SortableInterface;
use Mitch\LaravelDoctrine\Extensions\Sortable\Mapping\Annotation\Sortable as SortableAnnotation;
class SortableDriver extends AnnotationDriver {

    /**
     * Loads the metadata for the specified class into the provided container.
     *
     * @param string $className
     * @param ClassMetadata $metadata
     *
     * @return void
     */
    public function loadMetadataForClass($className, ClassMetadata $metadata)
    {
        $class = $metadata->getReflectionClass();
        if ( ! $class ) {
            // this happens when running annotation driver in combination with
            // static reflection services. This is not the nicest fix
            $class = new \ReflectionClass($metadata->name);
        }
        if( $class->implementsInterface(SortableInterface::class) )
        {
            $sortableAnnotation = $this->reader->getClassAnnotation($class, SortableAnnotation::class);

            $metadata->addExtensionData('Sortable',$sortableAnnotation);
        }
        return;
    }
}