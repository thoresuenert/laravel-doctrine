<?php namespace Mitch\LaravelDoctrine\Extensions\Sortable;


use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Mitch\LaravelDoctrine\Extensions\Sortable\Mapping\Annotation\Sortable as Annotation;


class SortableEventSubscriber implements EventSubscriber
{
    private $maxIndex;

    public function getSubscribedEvents()
    {
        return array(
            Events::prePersist,
            Events::postRemove,
            Events::preUpdate
        );
    }

    public function prePersist(LifecycleEventArgs $event)
    {
        $entity = $event->getEntity();

        if ($this->isNotSortable($entity))
            return;

        $class = get_class($entity);
        $meta = $event->getEntityManager()->getClassMetadata($class);
        $sortable = $meta->getExtensionData('Sortable');


        $em = $event->getEntityManager();

        // read maxIndex from database
        $this->getMaxIndex($class, $entity, $em, $sortable);

        // sanitize the index: -1, 0 , null or set to an integer
        $sanitizeIndex = $this->sanitizeIndex($this->getIndex($entity, $sortable));
        $this->setIndex($entity,$sortable, $sanitizeIndex);


        $dql = "UPDATE {$class} n";
        $dql .= " SET n.{$sortable->getIndexColumnName()} = n.{$sortable->getIndexColumnName()} + 1";
        $dql .= " WHERE n.{$sortable->getIndexColumnName()} >= {$sanitizeIndex}";

        if ($sortable->hasGroup()) {
            $group = $this->getGroup($entity,$sortable);
            $dql .= " AND n.{$sortable->getGroupColumnName()} = {$group}";
        }


        $q = $em->createQuery($dql);
        $q->getSingleScalarResult();
    }

    /**
     * @param PreUpdateEventArgs $event
     */
    public function preUpdate(PreUpdateEventArgs $event)
    {
        $entity = $event->getEntity();

        if ($this->isNotSortable($entity))
            return;

        $class = get_class($entity);
        $meta = $event->getEntityManager()->getClassMetadata($class);
        $sortable = $meta->getExtensionData('Sortable');


        // sortable index does not changed: do nothing
        if (!$event->hasChangedField($sortable->getIndexColumnName()))
            return;

        $em = $event->getEntityManager();

        // read maxIndex from database
        $this->getMaxIndex($class, $entity, $em, $sortable);

        // sanitize position
        $newValue = $event->getNewValue($sortable->getIndexColumnName());
        $newValue = $this->sanitizeIndex($newValue);
        $event->setNewValue($sortable->getIndexColumnName(), $newValue);

        // get old position for update query calculations
        $oldValue = $event->getOldValue($sortable->getIndexColumnName());

        if ($oldValue < $newValue) {
            $sign = '-';
            $params['lower'] = $oldValue;
            $params['upper'] = $newValue;
        } else {
            $sign = '+';
            $params['lower'] = $newValue;
            $params['upper'] = $oldValue;
        }

        $dql = "UPDATE {$class} n";
        $dql .= " SET n.{$sortable->getIndexColumnName()} = n.{$sortable->getIndexColumnName()} {$sign} 1";
        $dql .= " WHERE n.{$sortable->getIndexColumnName()} >= :lower";
        $dql .= " AND n.{$sortable->getIndexColumnName()} <= :upper";

        if ($sortable->hasGroup()) {
            $dql .= " AND n.{$sortable->getGroupColumnName()} = :group";
            $params['group'] = $this->getGroup($entity,$sortable);
        }



        $q = $em->createQuery($dql);
        $q->setParameters($params);
        $q->getSingleScalarResult();

    }

    public function postRemove(LifecycleEventArgs $event)
    {
        $entity = $event->getEntity();

        if ($this->isNotSortable($entity))
            return;

        $class = get_class($entity);
        $meta =  $event->getEntityManager()->getClassMetadata($class);
        $sortable = $meta->getExtensionData('Sortable');


        // build the update dql string
        $dql = "UPDATE {$class} n";
        $dql .= " SET n.{$sortable->getIndexColumnName()} = n.{$sortable->getIndexColumnName()} - 1";
        $dql .= " WHERE n.{$sortable->getIndexColumnName()} >= :lower";
        $params['lower'] = $this->getIndex($entity, $sortable);

        if ($sortable->hasGroup()) {
            $dql .= " AND n.{$sortable->getGroupColumnName()} = :group";
            $params['group'] = $this->getGroup($entity,$sortable);
        }
        // process dql query
        $em = $event->getEntityManager();
        $q = $em->createQuery($dql);
        $q->setParameters($params);
        $q->getSingleScalarResult();

    }


    private function sanitizeIndex($index)
    {
        if($index < 0 || $index > $this->maxIndex || is_null($index))
            return $this->maxIndex+1;

        return $index;
    }

    private function isNotSortable($entity)
    {
        return !$entity instanceof Sortable;
    }


    private function getMaxIndex($class, $entity, $em, Annotation $sortable)
    {
        $dql = "SELECT MAX(n.{$sortable->getIndexColumnName()})";
        $dql .= " FROM {$class} n";
        if($sortable->hasGroup())
        {
            $group = $this->getGroup($entity,$sortable);
            $dql .= " WHERE n.{$sortable->getGroupColumnName()} = {$group}";
        }

        $query = $em->createQuery($dql);
        $query->useQueryCache(false);
        $query->useResultCache(false);
        $maxIndex = $query->getSingleScalarResult();

        if (is_null($maxIndex)) $maxIndex = -1;
        $this->maxIndex = intval($maxIndex);
    }

    private function getGroup($entity, $sortable)
    {
        $group = $entity->{$sortable->getGroup()}();
        // check if group is an object
        if( is_object($group))
            return $group->getId();
        return $group;
    }
    private function getIndex($entity, $sortable)
    {
        return  $entity->{$sortable->getIndex()}();

    }

    private function setIndex($entity, $sortable, $sanitizeIndex)
    {
        $entity->{$sortable->setIndex()}($sanitizeIndex);
    }
}