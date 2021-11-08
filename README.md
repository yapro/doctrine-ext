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
