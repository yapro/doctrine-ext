<?php

declare(strict_types=1);

namespace YaPro\DoctrineExt\Hydrator;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use YaPro\Helper\JsonHelper;

class SimpleHydrator extends ArrayHydrator
{
    public const PRIMARY_KEY = 'id';
    private JsonHelper $jsonHelper;

    public function __construct(EntityManagerInterface $entityManager, JsonHelper $jsonHelper)
    {
        parent::__construct($entityManager);
        $this->jsonHelper = $jsonHelper;
        $this->setHydrateId(true);
    }

    public function fromArray($classNameOrObject, array $data, int $id = 0)
    {
        // if update data
        if ($id !== 0) {
            $data[self::PRIMARY_KEY] = $id;
        }

        return $this->hydrate($classNameOrObject, $data);
    }

    public function fromJson($classNameOrObject, string $data, int $id = 0)
    {
        $data = $this->jsonHelper->jsonDecode($data, true);

        return $this->fromArray($classNameOrObject, $data, $id);
    }

    protected function setProperty($entity, $propertyName, $value, $reflectionObject = null)
    {
        if (is_array($value)) {
            $value = new ArrayCollection($value);
        }

        return parent::setProperty($entity, $propertyName, $value, $reflectionObject);
    }

    private function getPropertyValue($object, $propertyName)
    {
        $reflectionClass = new \ReflectionClass($object);
        $reflectionProperty = $reflectionClass->getProperty($propertyName);
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($object);
    }

    private function findEntity($parentEntity, $parentEntityPropertyName, $id)
    {
        if (empty($parentEntityPropertyName)) {
            return null;
        }
        /** @var ArrayCollection $arrayCollection */
        $arrayCollection = $this->getPropertyValue($parentEntity, $parentEntityPropertyName);
        if ($arrayCollection->count() < 1) {
            return null;
        }
        foreach ($arrayCollection->getIterator() as $i => $object) {
            if ($id === $this->getPropertyValue($object, self::PRIMARY_KEY)) {
                return $object;
            }
        }

        return null;
    }

    /**
     * @param             $entity
     * @param array       $data
     * @param string      $parentEntityPropertyName
     * @param object|null $parentEntity
     *
     * @return mixed|object
     *
     * @throws \Exception
     */
    public function hydrate($entity, array $data, $parentEntity = null, $parentEntityPropertyName = '')
    {
        if (is_string($entity) && class_exists($entity)) {
            $id = 0;
            if (array_key_exists(self::PRIMARY_KEY, $data)) {
                $id = filter_var($data[self::PRIMARY_KEY], FILTER_VALIDATE_INT);
            }
            if (is_numeric($id) && $id > 0) {
                // если просто вызвать $this->entityManager->find($entity, $id) то доктрина создаст новые объекты для
                // строк, для которых уже создала объекты, следовательно, старые объекты будут заменены на новые, и затем
                // при flush доктрина будет думать, что старые объекты не используются и удалит строки из бд (тупая доктрина)
                $entityObject = $this->findEntity($parentEntity, $parentEntityPropertyName, $id);
                $entity = is_object($entityObject) ? $entityObject : $this->entityManager->find($entity, $id);
            } else {
                $entity = new $entity();
            }
        } elseif (!is_object($entity)) {
            throw new \Exception('Entity passed to ArrayHydrator::hydrate() must be a class name or entity object');
        }

        $entity = $this->hydrateProperties($entity, $data);
        $entity = $this->hydrateAssociations($entity, $data, $parentEntity);

        return $entity;
    }

    /**
     * @param             $entity
     * @param             $data
     * @param object|null $parentEntity
     *
     * @return mixed
     */
    protected function hydrateAssociations($entity, $data, $parentEntity = null)
    {
        $metaData = $this->entityManager->getClassMetadata(get_class($entity));
        foreach ($metaData->associationMappings as $fieldName => $mapping) {
            if (
                $parentEntity
                && get_class($parentEntity) === $mapping['targetEntity']
                // in_array($mapping['type'], [ClassMetadataInfo::ONE_TO_ONE, ClassMetadataInfo::MANY_TO_ONE])
            ) {
                $associationData = $parentEntity;
            } else {
                $associationData = $this->getAssociatedId($fieldName, $mapping, $data);
            }
            if (!empty($associationData)) {
                if (in_array($mapping['type'], [ClassMetadata::ONE_TO_ONE, ClassMetadata::MANY_TO_ONE], true)) {
                    $entity = $this->hydrateToOneAssociation($entity, $fieldName, $mapping, $associationData);
                }

                if (in_array($mapping['type'], [ClassMetadata::ONE_TO_MANY, ClassMetadata::MANY_TO_MANY], true)) {
                    $entity = $this->hydrateToManyAssociation($entity, $fieldName, $mapping, $associationData);
                }
            }
        }

        return $entity;
    }

    /**
     * @param $entity
     * @param $propertyName
     * @param $mapping
     * @param $value
     *
     * @return mixed
     */
    protected function hydrateToManyAssociation($entity, $propertyName, $mapping, $value)
    {
        $reflectionObject = new \ReflectionObject($entity);
        $values = is_array($value) ? $value : [$value];

        $assocationObjects = [];
        foreach ($values as $value) {
            if (is_array($value)) {
                $assocationObjects[] = $this->hydrate($mapping['targetEntity'], $value, $entity, $propertyName);
            } elseif ($associationObject = $this->fetchAssociationEntity($mapping['targetEntity'], $value)) {
                $assocationObjects[] = $associationObject;
            }
        }

        $entity = $this->setProperty($entity, $propertyName, $assocationObjects, $reflectionObject);

        return $entity;
    }

    /**
     * @param $entity
     * @param $propertyName
     * @param $mapping
     * @param $value
     *
     * @return mixed
     */
    protected function hydrateToOneAssociation($entity, $propertyName, $mapping, $value)
    {
        $reflectionObject = new \ReflectionObject($entity);
        if (is_object($value)) {
            return $this->setProperty($entity, $propertyName, $value, $reflectionObject);
        }

        $toOneAssociationObject = $this->fetchAssociationEntity($mapping['targetEntity'], $value);
        if (!is_null($toOneAssociationObject)) {
            $entity = $this->setProperty($entity, $propertyName, $toOneAssociationObject, $reflectionObject);
        }

        return $entity;
    }
}
