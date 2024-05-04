# Doctrine extensions

## Installation

Add as a requirement in your `composer.json` file or run
```sh
composer require yapro/doctrine-ext dev-master
```

## Usage

* AutoFillFieldsTrait - for Entities with an auto-generated ID (extends RequiredFieldsTrait)
* RequiredFieldsTrait - for Entities without an auto-generated ID (fields: createdAt, updatedAt)
* EntityAutoFillTimeListener - for auto fill fields (createdAt, updatedAt) of any Entities
* ImportedObjectInterface - for specific auto fill fields (createdAt, updatedAt) of any Entities
* ReloadDatabaseTrait - for Entities testing / data testing

Example to usage ReloadDatabaseTrait
```php

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use YaPro\DoctrineExt\ReloadDatabaseTrait;

class ExampleClassTest extends KernelTestCase
{
    use ReloadDatabaseTrait;

    protected static EntityManagerInterface $entityManager;

    public static function setUpBeforeClass()
    {
        self::$entityManager = self::$container->get(EntityManagerInterface::class);
    }
    
    public function myTest()
    {
        $this->truncateClass('My\User');
        $this->truncateTable('user_orders');
        $this->truncateAllTables();
        
        // ... some useful actions
    }
}
```
* BigIntType - for native bigint supporting, example to configure:
```yaml
doctrine:
    dbal:
        types:
            bigint: YaPro\DoctrineExt\DbalType\BigIntType
```
and usage:
```php
<?php

namespace App\Entity;
class MyEntity
{
    #[ORM\Column(type: Types::BIGINT)]
    private int $mybigint = 0;
```

* DBALConnectionWrapper - for simple repeat a query for get a number of total rows, example to configure and usage:
```yaml
doctrine:
    dbal:
        default_connection: my_connection
        connections:
            my_connection:
                wrapper_class: YaPro\DoctrineExt\Wrapping\DBALConnectionWrapper
                host:     '%env(MYSQL_HOST)%'
                port:     '%env(MYSQL_PORT)%'
                dbname:   '%env(MYSQL_DATABASE)%'
                user:     '%env(MYSQL_USERNAME)%'
                password: '%env(MYSQL_PASSWORD)%'
                driver: 'pdo_mysql'
                server_version: '5'
```
Usage:
```php
$items = $this->getEntityManager()->getConnection()->fetchAll("
    SELECT 
        id,
        title,
        createdAt
    FROM Article
    WHERE isShow = 1
    ORDER BY createdAt DESC
    LIMIT 20, 10
");

// get the total number of items like: SELECT COUNT(*) FROM Article WHERE isShow = 1
echo $this->getEntityManager()->getConnection()->fetchColumn(DBALConnectionWrapper::SELECT_FOUND_ROWS);
// if you use TotalItemsTrait you can call:
echo $this->getTotalItems();
```

Example to configure EntityAutoFillTimeListener
```yaml
    YaPro\DoctrineExt\EventListener\EntityAutoFillTimeListener:
        tags:
            - { name: doctrine.event_listener, event: prePersist }
            - { name: doctrine.event_listener, event: preUpdate }
```
