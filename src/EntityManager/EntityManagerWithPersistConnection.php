<?php

declare(strict_types=1);

namespace YaPro\DoctrineExt\EntityManager;

use Doctrine\ORM\EntityManager;

class EntityManagerWithPersistConnection extends EntityManager
{
    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        // Когда бд не может выполнить SQL, Doctrine выбрасывает исключение и закрывает соединение с базкой.
        // Для ситуаций, когда потеря одной записи не проблема, так делать нельзя, поэтому теперь подключение остается.
    }
}
