<?php namespace Mitch\LaravelDoctrine\Extensions\SoftDeleteable;

use Doctrine\ORM\Event\OnFlushEventArgs;
use DateTime;

class SoftDeleteableListener
{
    public function onFlush(OnFlushEventArgs $event)
    {
        $entityManager = $event->getEntityManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if ($this->isSoftDeletable($entity)) {
                $metadata = $entityManager->getClassMetadata(get_class($entity));
                $oldDeletedAt = $metadata->getFieldValue($entity, 'deletedAt');
                if ($oldDeletedAt instanceof DateTime) {
                    continue;
                }
                $now = new DateTime;
                $metadata->setFieldValue($entity, 'deletedAt', $now);
                $entityManager->persist($entity);

                $unitOfWork->propertyChanged($entity, 'deletedAt', $oldDeletedAt, $now);
                $unitOfWork->scheduleExtraUpdate($entity, [
                    'deletedAt' => [$oldDeletedAt, $now]
                ]);
            }
        }
    }

    private function isSoftDeletable($entity)
    {
        return array_key_exists('Mitch\LaravelDoctrine\Extensions\SoftDeleteable\SoftDeletesTrait', class_uses($entity));
    }
}