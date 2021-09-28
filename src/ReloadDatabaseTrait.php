<?php

declare(strict_types=1);

namespace YaPro\DoctrineExt;

use Doctrine\ORM\EntityManagerInterface;

trait ReloadDatabaseTrait
{
    /*
     * Do not forget to init trait`s dependency:

    protected static EntityManagerInterface $entityManager;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }
    */

    /**
     * @param string $className
     */
    protected static function truncateClassStatic(string $className)
    {
        static::truncateTableStatic(self::$entityManager->getClassMetadata($className)->getTableName());
    }

    protected static function truncateTableStatic(string $tableName)
    {
        // без каскадного удаления вылазит ошибка: SQLSTATE[0A000]: Feature not supported: 7 ERROR:  cannot truncate a
        // table referenced in a foreign key constraint
        static::$entityManager->getConnection()->exec(
            'SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE ' . $tableName . '; SET FOREIGN_KEY_CHECKS = 1;'
        );
    }

    protected function truncateAllTables()
    {
        $sql = 'SET FOREIGN_KEY_CHECKS = 0;';
        foreach (self::$entityManager->getConnection()->getSchemaManager()->listTableNames() as $tableName) {
            $sql .= 'TRUNCATE TABLE ' . $tableName . ';';
        }
        $sql .= 'SET FOREIGN_KEY_CHECKS = 1;';
        static::$entityManager->getConnection()->exec($sql);
    }

    protected function truncateTable(string $tableName)
    {
        static::truncateTableStatic($tableName);
    }

    protected function truncateClass(string $className)
    {
        static::truncateClassStatic($className);
    }

    protected static function truncateAllTablesInSqLite()
    {
        $sql = '';
        foreach (self::$entityManager->getConnection()->getSchemaManager()->listTableNames() as $tableName) {
            $sql .= 'DELETE FROM ' . $tableName . ';';
            $sql .= 'DELETE FROM SQLITE_SEQUENCE WHERE name="' . $tableName . '";';
        }
        static::$entityManager->getConnection()->exec($sql);
    }
}
