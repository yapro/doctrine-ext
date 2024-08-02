<?php

declare(strict_types=1);

namespace YaPro\DoctrineExt\Tests\Functional;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Deprecations\Deprecation;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use YaPro\DoctrineExt\Hydrator\SimpleHydrator;
use YaPro\DoctrineExt\Tests\Entity\Article;
use YaPro\DoctrineExt\Tests\Entity\Comment;
use YaPro\Helper\JsonHelper;

class SimpleHydratorTest extends TestCase
{
    private static SimpleHydrator $SimpleHydrator;
    private static EntityManagerInterface $entityManager;

    public static function setUpBeforeClass(): void
    {
        self::$entityManager = self::getEm();
        self::$SimpleHydrator = new SimpleHydrator(self::$entityManager, new JsonHelper());
    }

    private static function getEm(): EntityManagerInterface
    {
        AnnotationRegistry::loadAnnotationClass(Groups::class);
        AnnotationRegistry::loadAnnotationClass(MaxDepth::class);
        // https://www.doctrine-project.org/projects/doctrine-orm/en/2.9/tutorials/getting-started.html#obtaining-the-entitymanager
        // Create a simple "default" Doctrine ORM configuration for Annotations
        $entities = [__DIR__ . '/../Entity'];
        $isDevMode = true;
        $proxyDir = null;
        $cache = null;
        $useSimpleAnnotationReader = false;
        // $config = ORMSetup::createAnnotationMetadataConfiguration($entities, $isDevMode, $proxyDir, $cache, $useSimpleAnnotationReader);
        $reportFieldsWhereDeclared = true;
        $config = ORMSetup::createAttributeMetadataConfiguration($entities, $isDevMode, $proxyDir, $cache, $reportFieldsWhereDeclared);
        $config->setLazyGhostObjectEnabled(true); // чтобы не срабатывал Deprecation::trigger про https://github.com/doctrine/orm/pull/10837/
        // database configuration parameters
        $dbPath = __DIR__ . '/../../vendor/bin/db.sqlite';
        touch($dbPath);
        chmod($dbPath, 0777);
        $conn = [
            // https://www.sqlitetutorial.net/sqlite-commands/
            'driver' => 'pdo_sqlite',
            'path' => $dbPath,
        ];
        $connection = DriverManager::getConnection($conn, $config);

        // obtaining the entity manager
        return new EntityManager($connection, $config);
    }

    private function createSchema()
    {
        $metadataArticle = self::$entityManager->getClassMetadata(Article::class);
        $metadataComment = self::$entityManager->getClassMetadata(Comment::class);
        $schemaTool = new SchemaTool(self::$entityManager);
        // you can drop the table like this if necessary
        $schemaTool->dropSchema([$metadataArticle, $metadataComment]);
        $schemaTool->createSchema([$metadataArticle, $metadataComment]);
    }

    public function testCreate(): Article
    {
        $assert = function ($object) {
            $this->assertTrue($object instanceof Article);
            $this->assertTrue($object->getParentId() === 12);
            $this->assertTrue($object->getTitle() === 'title1');
            $this->assertTrue($object->getComments() instanceof Collection);
            $this->assertTrue($object->getComments()->count() === 2);
            $this->assertTrue($object->getComments()->first() instanceof Comment);
            $this->assertTrue($object->getComments()->first()->getParentId() === 23);
            $this->assertTrue($object->getComments()->first()->getMessage() === 'str1');
            $this->assertTrue($object->getComments()->last() instanceof Comment);
            $this->assertTrue($object->getComments()->last()->getParentId() === 34);
            $this->assertTrue($object->getComments()->last()->getMessage() === 'str2');
        };

        $json = '{"parentId": 12, "title": "title1", "comments": [{"parentId": 23, "message": "str1"}, {"parentId": 34, "message": "str2"}]}';
        /** @var Article $object */
        $object = self::$SimpleHydrator->fromJson(Article::class, $json);
        $assert($object);
        $this->assertTrue($object->getId() === null);
        $this->assertTrue($object->getComments()->first()->getId() === null);
        $this->assertTrue($object->getComments()->last()->getId() === null);
        $this->createSchema();
        self::$entityManager->persist($object);
        self::$entityManager->flush();
        self::$entityManager->refresh($object);
        $assert($object);
        $this->assertTrue($object->getId() === 1);
        $this->assertTrue($object->getComments()->first()->getId() === 1);
        $this->assertTrue($object->getComments()->last()->getId() === 2);

        return $object;
    }

    /**
     * @depends testCreate
     */
    public function testUpdate1(Article $article): Article
    {
        /** @var Comment $firstComment */
        $firstComment = $article->getComments()->first();
        /** @var Comment $lastComment */
        $lastComment = $article->getComments()->last();

        $assert = function ($object) use ($article, $firstComment, $lastComment) {
            $this->assertTrue($object instanceof Article);
            $this->assertTrue($object->getId() === $article->getId());
            $this->assertTrue($object->getParentId() === $article->getParentId());
            $this->assertTrue($object->getTitle() === 'title2');

            $this->assertTrue($object->getComments() instanceof Collection);
            $this->assertTrue($object->getComments()->count() === 2);

            $this->assertTrue($object->getComments()->first() instanceof Comment);
            $this->assertTrue($object->getComments()->first()->getId() === $firstComment->getId());
            $this->assertTrue($object->getComments()->first()->getParentId() === $firstComment->getParentId()); // мы не меняли данное поле
            $this->assertTrue($object->getComments()->first()->getMessage() === 'str3');

            $this->assertTrue($object->getComments()->last() instanceof Comment);
            $this->assertTrue($object->getComments()->last()->getId() === $lastComment->getId());
            $this->assertTrue($object->getComments()->last()->getParentId() === 45);
            $this->assertTrue($object->getComments()->last()->getMessage() === 'str4');
        };

        // обновляем 2-а комментария (обновляя в первом только поле message)
        $json = '{"title": "title2", "comments": [{"id": ' . $firstComment->getId() . ', "message": "str3"}, {"id": ' . $lastComment->getId() . ', "parentId": 45, "message": "str4"}]}';
        /** @var Article $object */
        $object = self::$SimpleHydrator->fromJson(Article::class, $json, $article->getId());
        $assert($object);
        self::$entityManager->flush();
        self::$entityManager->refresh($object);
        $assert($object);

        return $object;
    }

    /**
     * @depends testUpdate1
     */
    public function testUpdate2(Article $article): Article
    {
        /** @var Comment $firstComment */
        $firstComment = $article->getComments()->first();
        /** @var Comment $lastComment */
        $lastComment = $article->getComments()->last();

        $assert = function ($object) use ($article, $firstComment) {
            $this->assertTrue($object instanceof Article);
            $this->assertTrue($object->getId() === $article->getId());
            $this->assertTrue($object->getParentId() === 13);
            $this->assertTrue($object->getTitle() === $article->getTitle());

            $this->assertTrue($object->getComments() instanceof Collection);
            $this->assertTrue($object->getComments()->first() instanceof Comment);
            $this->assertTrue($object->getComments()->first()->getId() === $firstComment->getId());
            $this->assertTrue($object->getComments()->first()->getParentId() === $firstComment->getParentId()); // мы не меняли данное поле
            $this->assertTrue($object->getComments()->first()->getMessage() === 'str5');
        };

        // 1 комментарий обновляем, 2-ой комментарий не будет изменен
        $json = '{"parentId": 13, "comments": [{"id": ' . $firstComment->getId() . ', "message": "str5"}]}';
        /** @var Article $object */
        $object = self::$SimpleHydrator->fromJson(Article::class, $json, $article->getId());
        $assert($object);
        $this->assertTrue($object->getComments()->count() === 1);
        self::$entityManager->flush();
        self::$entityManager->refresh($object);
        $assert($object);
        $this->assertTrue($object->getComments()->count() === 2);
        $this->assertTrue($object->getComments()->last() instanceof Comment);
        $this->assertTrue($object->getComments()->last()->getId() === $lastComment->getId());
        $this->assertTrue($object->getComments()->last()->getParentId() === $lastComment->getParentId());
        $this->assertTrue($object->getComments()->last()->getMessage() === $lastComment->getMessage());

        return $object;
    }

    /**
     * @depends testUpdate2
     */
    public function testUpdate3(Article $article): Article
    {
        /** @var Comment $firstComment */
        $firstComment = $article->getComments()->first();
        /** @var Comment $secondComment */
        $secondComment = $article->getComments()->last();

        $assert = function ($object) use ($article) {
            $this->assertTrue($object instanceof Article);
            $this->assertTrue($object->getId() === $article->getId());
            $this->assertTrue($object->getParentId() === $article->getParentId());
            $this->assertTrue($object->getTitle() === $article->getTitle());

            $this->assertTrue($object->getComments() instanceof Collection);
            $this->assertTrue($object->getComments()->last() instanceof Comment);
            $this->assertTrue($object->getComments()->last()->getParentId() === 0);
            $this->assertTrue($object->getComments()->last()->getMessage() === 'str6');
        };

        // 2-а комментария не будут обновлены, 1 комментарий будет добавлен
        $json = '{"comments": [{"message": "str6"}]}';
        /** @var Article $object */
        $object = self::$SimpleHydrator->fromJson(Article::class, $json, $article->getId());
        $assert($object);
        $this->assertTrue($object->getComments()->count() === 1);
        $this->assertTrue($object->getComments()->last()->getId() === null);
        self::$entityManager->flush();
        $this->assertTrue($object->getComments()->last()->getId() === 3);
        self::$entityManager->refresh($object);
        $assert($object);
        $this->assertTrue($object->getComments()->count() === 3);
        $this->assertTrue($object->getComments()->first() instanceof Comment);
        $this->assertTrue($object->getComments()->first()->getId() === $firstComment->getId());
        $this->assertTrue($object->getComments()->first()->getParentId() === $firstComment->getParentId());
        $this->assertTrue($object->getComments()->first()->getMessage() === $firstComment->getMessage());
        $this->assertTrue($object->getComments()->get(1) instanceof Comment);
        $this->assertTrue($object->getComments()->get(1)->getId() === $secondComment->getId());
        $this->assertTrue($object->getComments()->get(1)->getParentId() === $secondComment->getParentId());
        $this->assertTrue($object->getComments()->get(1)->getMessage() === $secondComment->getMessage());

        return $object;
    }

    /**
     * @depends testUpdate3
     */
    public function testUpdate4(Article $article)
    {
        $this->assertTrue(true);
        // 1-ый комментарий будет обновлен, еще один комментарий будет добавлен, остальные комментарии будут удалены
        // $json = '{"parentId": 2, "title": "title2", "comments": [{"id": ' . $firstCommentId . ', "message": "str7"},{"message": "str8"}, {"hint":"delete not specified"}]}';
        /* @var Article $object */
        // $object = self::$dataConverter->fromJson(Article::class, $json, $article->getId());
        // self::$entityManager->flush();
        // self::$entityManager->refresh($object);
    }
}
