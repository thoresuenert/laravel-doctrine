<?php namespace Mitch\LaravelDoctrine\Extensions\Sortable;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Mitch\LaravelDoctrine\Extensions\Sortable\Mapping\Annotation\Sortable as Annotation;


/**
 * Class SortableEventSubscriber
 * @package Mitch\LaravelDoctrine\Extensions\Sortable
 */
class SortableEventSubscriber implements EventSubscriber
{
    /**
     * @var
     */
    private $maxIndex;

    /**
     * @return array
     */
    public function getSubscribedEvents()
    {
        return array(
            Events::prePersist,
            Events::postRemove,
            Events::preUpdate
        );
    }
    /**
     * @param LifecycleEventArgs $event
     * @param $entity
     * @return array
     */
    protected function boot(LifecycleEventArgs $event, $entity)
    {
        $em = $event->getEntityManager();
        $class = get_class($entity);
        $meta = $event->getEntityManager()->getClassMetadata($class);
        $sortable = $meta->getExtensionData('Sortable');
        $indexFieldName = $sortable->getIndexFieldName();
        $groupFieldName = $sortable->getGroupFieldName();
        return array($em, $class, $meta, $sortable, $indexFieldName, $groupFieldName);
    }

    /**
     * @param LifecycleEventArgs $event
     */
    public function prePersist(LifecycleEventArgs $event)
    {
        $entity = $event->getEntity();
        if ($this->isNotSortable($entity))
            return;

        list($em, $class, $meta, $sortable, $indexFieldName, $groupFieldName) = $this->boot($event, $entity);

        // read maxIndex from database
        $this->getMaxIndex($class, $entity, $em, $sortable, $meta);

        // sanitize the index: -1, 0 , null or set to an integer
        $indexValue = $meta->getFieldValue($entity, $indexFieldName );
        $indexValue = $this->sanitizeIndex($indexValue);
        $meta->setFieldValue($entity, $indexFieldName, $indexValue);

        $queryBuilder = $em->createQueryBuilder();
        $queryBuilder->update($class, "n")
            ->set("n.{$indexFieldName}","n.{$indexFieldName} + 1")
            ->where("n.{$indexFieldName} >= :index")
            ->setParameter('index', $indexValue);

        if ($sortable->hasGroup()) {
            $queryBuilder->andWhere("n.{$groupFieldName} = :group")
                ->setParameter('group', $this->getGroup($em, $entity,$sortable, $meta) );
        }

        $query = $queryBuilder->getQuery();
        $query->getSingleScalarResult();
    }

    /**
     * @param PreUpdateEventArgs $event
     */
    public function preUpdate(PreUpdateEventArgs $event)
    {
        $entity = $event->getEntity();

        if ($this->isNotSortable($entity))
            return;

        list($em, $class, $meta, $sortable, $indexFieldName, $groupFieldName) = $this->boot($event, $entity);

        // sortable index does not changed: do nothing
        if (!$event->hasChangedField($indexFieldName))
            return;

        // read maxIndex from database
        $this->getMaxIndex($class, $entity, $em, $sortable, $meta);

        // sanitize position
        $newValue = $event->getNewValue($indexFieldName);
        $newValue = $this->sanitizeIndex($newValue);
        $event->setNewValue($indexFieldName, $newValue);

        // get old position for update query calculations
        $oldValue = $event->getOldValue($indexFieldName);

        if ($oldValue < $newValue) {
            $sign = '-'; $lower = $oldValue; $upper = $newValue;
        } else {
            $sign = '+'; $lower = $newValue; $upper = $oldValue;
        }

        $queryBuilder = $em->createQueryBuilder();
        $queryBuilder->update($class,"n")
            ->set("n.{$indexFieldName}", "n.{$indexFieldName} {$sign} 1")
            ->where("n.{$indexFieldName} >= :lower")
            ->andWhere("n.{$indexFieldName} <= :upper")
            ->setParameters([
                "lower" => $lower,
                "upper" => $upper
            ]);

        if ($sortable->hasGroup()) {
            $queryBuilder->andWhere("n.{$groupFieldName} = :group")
                ->setParameter('group', $this->getGroup($em, $entity,$sortable, $meta) );
        }

        $query = $queryBuilder->getQuery();
        $query->getSingleScalarResult();
    }

    /**
     * @param LifecycleEventArgs $event
     */
    public function postRemove(LifecycleEventArgs $event)
    {
        $entity = $event->getEntity();

        if ($this->isNotSortable($entity))
            return;

        list($em, $class, $meta, $sortable, $indexFieldName, $groupFieldName) = $this->boot($event, $entity);

        // build the update dql string
        $queryBuilder = $em->createQueryBuilder();
        $queryBuilder->update($class, "n")
            ->set("n.{$indexFieldName}", "n.{$indexFieldName} - 1")
            ->where("WHERE n.{$indexFieldName} >= :lower")
            ->setParameter('lower', $meta->getFieldValue($entity, $indexFieldName));

        if ($sortable->hasGroup()) {
            $queryBuilder->andWhere("n.{$groupFieldName} = :group")
                ->setParameter('group', $this->getGroup($em, $entity, $groupFieldName, $meta));
        }
        // process dql query
        $query = $queryBuilder->getQuery();
        $query->getSingleScalarResult();

    }


    /**
     * Index can be 0, -1, NULL or defined <= maxIndex
     * @param $index
     * @return mixed
     */
    private function sanitizeIndex($index)
    {
        if($index < 0 || $index > $this->maxIndex || is_null($index))
            return $this->maxIndex+1;

        return $index;
    }

    /**
     * @param $entity
     * @return bool
     */
    private function isNotSortable($entity)
    {
        return !$entity instanceof Sortable;
    }


    /**
     * Get max index from database for entity by group if needed
     * @param $class
     * @param $entity
     * @param $em
     * @param Annotation $sortable
     * @param $meta
     */
    private function getMaxIndex($class, $entity, $em, Annotation $sortable, $meta)
    {
        $queryBuilder = $em->createQueryBuilder();
        $queryBuilder->select("MAX(n.{$sortable->getIndexFieldName()})")->from($class,'n');

        if($sortable->hasGroup())
        {
            $groupFieldName = $sortable->getGroupFieldName();
            $queryBuilder->where("n.{$groupFieldName} = :group")
                ->setParameter(':group', $this->getGroup($em, $entity, $groupFieldName, $meta));
        }

        $query = $queryBuilder->getQuery();
        $query->useQueryCache(false);
        $query->useResultCache(false);
        $maxIndex = $query->getSingleScalarResult();

        if (is_null($maxIndex)) $maxIndex = -1;
        $this->maxIndex = intval($maxIndex);
    }

    /**
     * Get group value, if association get id
     * @param $em
     * @param $entity
     * @param $groupFieldName
     * @param $meta
     * @return mixed
     */
    private function getGroup($em, $entity, $groupFieldName, $meta)
    {
        $group = $meta->getFieldValue($entity, $groupFieldName);
        // if the group is an associated object
        // we need to resolve the identifier from the object
        if( is_object($group))
        {
            $meta = $em->getClassMetadata(get_class($group));
            return $meta->getSingleIdReflectionProperty()->getValue($group);
        }

        return $group;
    }


}