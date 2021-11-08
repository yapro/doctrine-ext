<?php

declare(strict_types=1);

namespace YaPro\DoctrineExt\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use YaPro\DoctrineExt\Marker\ImportedObjectInterface;

/**
 * Авто-установка CreatedAt / UpdatedAt
 * - "кастомная" реализация трейта HasLifecycleCallbacks
 */
class EntityAutoFillTimeListener
{
    public function prePersist(LifecycleEventArgs $event): void
    {
        $entity = $event->getEntity();
        // сущность импортируемая - заполняем только если пусто и только если данные не указаны
        if ($entity instanceof ImportedObjectInterface) {
            if (!$entity->getCreatedAt()) {
                $entity->setCreatedAt(new \DateTimeImmutable());
            }
            if (!$entity->getUpdatedAt()) {
                $entity->setUpdatedAt(new \DateTimeImmutable());
            }

            return;
        }

        $entity
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable())
        ;
    }

    public function preUpdate(LifecycleEventArgs $event): void
    {
        $entity = $event->getEntity();
        if (!$entity instanceof ImportedObjectInterface) {
            // применяем только к неимпортируемым сущностям
            $entity->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
