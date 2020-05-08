# Doctrine extensions

## Installation

Add as a requirement in your `composer.json` file or run
```sh
composer require yapro/doctrine-ext dev-master
```

## Usage

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
