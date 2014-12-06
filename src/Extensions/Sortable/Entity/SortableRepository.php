<?php namespace Mitch\LaravelDoctrine\Extensions\Sortable\Entity;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Gedmo\Exception\InvalidMappingException;
use Mitch\LaravelDoctrine\Extensions\Sortable\Sortable;

class SortableRepository extends EntityRepository{

    private $annoation;

    public function __construct(EntityManager $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);

        $refClass = $class->getReflectionClass();
        if ( ! $refClass ) {
            // this happens when running annotation driver in combination with
            // static reflection services. This is not the nicest fix
            $refClass = new \ReflectionClass($class->name);
        }
        if( ! $refClass->implementsInterface(Sortable::class) )
            throw new InvalidMappingException('This repository can be attached only to ORM Sortable');

        $this->annoation = $class->getExtensionData('Sortable');
    }

    public function getBySortableGroupsQuery(array $groupValues=array())
    {
        return $this->getBySortableGroupsQueryBuilder($groupValues)->getQuery();
    }

    public function getBySortableGroupsQueryBuilder(array $groupValues=array())
    {
        $qb = $this->createQueryBuilder('n');
        $qb->orderBy('n.'.$this->annoation->getIndexColumnName());

        if($this->annoation->hasGroup())
        {
            $qb->andWhere('n.'.$this->annoation->getGroupColumnName().' in :groups')
                ->setParameter('groups', $groupValues);
        }

        return $qb;
    }

    public function getBySortableGroups(array $groupValues=array())
    {
        $query = $this->getBySortableGroupsQuery($groupValues);
        return $query->getResult();
    }
}