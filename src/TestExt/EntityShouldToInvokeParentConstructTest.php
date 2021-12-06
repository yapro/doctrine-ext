<?php

declare(strict_types=1);

namespace YaPro\DoctrineExt\TestExt;

use Doctrine\ORM\EntityManagerInterface;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EntityShouldToInvokeParentConstructTest extends KernelTestCase
{
    private static EntityManagerInterface $dbObjectManager;

    public function setUp()
    {
        parent::setUp();
        self::bootKernel();
        self::$dbObjectManager = self::$container->get('doctrine')->getManager();
    }

    public function testConstruct(): void
    {
        $entities = self::$dbObjectManager->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
        foreach ($entities as $className) {
            // прекращаем проверку, если класс не имеет родителей (extends)
            if (!class_parents($className)) {
                continue;
            }

            // прекращаем проверку, если родительский класс не имеет __construct
            $parentClass = get_parent_class($className);
            if (!method_exists($parentClass, '__construct')) {
                continue;
            }

            // прекращаем проверку, если класс не имеет метода __construct
            if (!method_exists($className, '__construct')) {
                continue;
            }

            // находим имя самого дочернего класса имеющего метод __construct
            $reflectionMethod = new ReflectionMethod($className, '__construct');
            if ($className === $reflectionMethod->getDeclaringClass()->getName()) {
                $methodSource = $this->getMethodSource($reflectionMethod);
                $this->assertStringContainsString(
                    'parent::__construct',
                    $methodSource,
                    'Entity should to invoke parent::__construct().'
                );
            }
        }
    }

    /**
     * @param ReflectionMethod $reflectionMethod
     *
     * @return string
     */
    private function getMethodSource(ReflectionMethod $reflectionMethod): string
    {
        $fileContent = file($reflectionMethod->getFileName());
        $firstLine = $reflectionMethod->getStartLine();
        $length = $reflectionMethod->getEndLine() - $firstLine;

        return implode('', array_slice($fileContent, $firstLine, $length));
    }
}
