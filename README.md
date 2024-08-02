# Doctrine extensions

![lib tests](https://github.com/yapro/doctrine-ext/actions/workflows/main.yml/badge.svg)

This is a library for resolving common doctrine problems.

## Installation

Add as a requirement in your `composer.json` file or run
```sh
composer require yapro/doctrine-ext
```

As dev:
```sh
composer require apro/doctrine-ext dev-master
```

## What inside

1. Array to Doctrine Entity Hydrator
2. BigIntType - for native bigint supporting
3. ReloadDatabaseTrait - for Entities testing / data testing
4. EntityShouldToInvokeParentConstructTest - a test that verifies your entity that extending other class
5. EntityAutoFillTimeListener - for autofill fields (createdAt, updatedAt) of any Entities
6. ImportedObjectInterface - for specific autofill fields (createdAt, updatedAt) of any Entities
7. RequiredFieldsTrait - for Entities without an auto-generated ID (fields: createdAt, updatedAt)
8. AutoIdAndRequiredFieldsTrait - for Entities with an auto-generated ID (extends RequiredFieldsTrait)

### Array to Doctrine Entity Hydrator

You can populate this doctrine entity object with an array, for example:

```PHP
$data = [
    'name'        => 'Fred Jones',
    'email'       => 'fred@example.com',
    'company'     => 2,
    'permissions' => [1, 2, 3, 4]
];

$hydrator = new \YaPro\DoctrineExt\Hydrator\ArrayHydrator($entityManager);
$entity   = $hydrator->hydrate('App\Entity\User', $data);
```

You can even populate user with JSON API resource data ( [documentation](http://jsonapi.org/format/#document-resource-objects) )
```PHP
$data = [
    'attributes'    => [
        'name'  => 'Fred Jones',
        'email' => 'fred@example.com',
    ],
    'relationships' => [
        'company'     => [
            'data' => ['id' => 1, 'type' => 'company'],
        ],
        'permissions' => [
            'data' => [
                ['id' => 1, 'type' => 'permission'],
                ['id' => 2, 'type' => 'permission'],
                ['id' => 3, 'type' => 'permission'],
                ['id' => 4, 'type' => 'permission'],
                ['name' => 'New permission']
            ]
        ]
    ]
];
    
$hydrator = new \YaPro\DoctrineExt\Hydrator\JsonApiHydrator($entityManager);
$entity   = $hydrator->hydrate('App\Entity\User', $data);
```
or like that:
```php
$json = '{
   "parentId": 12, 
   "title": "title1", 
   "comments": [{"parentId": 23, "message": "str1"}, {"parentId": 34, "message": "str2"}]
}';
$hydrator = new \YaPro\DoctrineExt\Hydrator\SimpleHydrator($entityManager, new \YaPro\Helper\JsonHelper());
$entity   = $hydrator->fromJson(Article::class, $json);
```
See [more examples](tests/Functional/SimpleHydratorTest.php)

Notice: Doctrine ORM v2 is not supported after removing the [pmill/doctrine-array-hydrator](https://github.com/yapro/doctrine-ext/commit/efe74ed4df79f7450ff2e437cdab5e1ee3afae2a#diff-d2ab9925cad7eac58e0ff4cc0d251a937ecf49e4b6bf57f8b95aab76648a9d34L18) dependency.

### ReloadDatabaseTrait

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

### BigIntType

BigIntType - native php bigint supporting, example to configure:
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

### DBALConnectionWrapper

Simple way to repeat a query for get a number of total rows, example to configure and usage:
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

Dev
------------
```sh
docker build -t yapro/doctrine-ext:latest -f ./Dockerfile ./
docker run --rm --user=$(id -u):$(id -g) --add-host=host.docker.internal:host-gateway -it --rm -v $(pwd):/app -w /app yapro/doctrine-ext:latest bash
cp -f composer.lock.php8 composer.lock
composer install -o
```
Debug tests:
```sh
PHP_IDE_CONFIG="serverName=common" \
XDEBUG_SESSION=common \
XDEBUG_MODE=debug \
XDEBUG_CONFIG="max_nesting_level=200 client_port=9003 client_host=host.docker.internal" \
vendor/bin/simple-phpunit --cache-result-file=/tmp/phpunit.cache -v --stderr --stop-on-incomplete --stop-on-defect \
--stop-on-failure --stop-on-warning --fail-on-warning --stop-on-risky --fail-on-risky
```

Cs-Fixer:
```sh
wget https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/releases/download/v3.61.1/php-cs-fixer.phar && chmod +x ./php-cs-fixer.phar
./php-cs-fixer.phar fix --config=.php-cs-fixer.dist.php -v --using-cache=no --allow-risky=yes
```

Update phpmd rules:
```shell
wget https://github.com/phpmd/phpmd/releases/download/2.12.0/phpmd.phar && chmod +x ./phpmd.phar
/app/vendor/phpmd/phpmd/src/bin/phpmd . text phpmd.xml --exclude .github/workflows,vendor --strict --generate-baseline
```
