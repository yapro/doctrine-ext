<?php

namespace YaPro\DoctrineExt\Hydrator;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Exception;

class ArrayHydrator
{
    /**
     * The keys in the data array are entity field names
     */
    const HYDRATE_BY_FIELD = 1;

    /**
     * The keys in the data array are database column names
     */
    const HYDRATE_BY_COLUMN = 2;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * If true, then associations are filled only with reference proxies. This is faster than querying them from
     * database, but if the associated entity does not really exist, it will cause:
     * * The insert/update to fail, if there is a foreign key defined in database
     * * The record ind database also pointing to a non-existing record
     *
     * @var bool
     */
    protected $hydrateAssociationReferences = true;

    /**
     * Tells whether the input data array keys are entity field names or database column names
     *
     * @var int one of ArrayHydrator::HIDRATE_BY_* constants
     */
    protected $hydrateBy = self::HYDRATE_BY_FIELD;

    /**
     * If true, hydrate the primary key too. Useful if the primary key is not automatically generated by the database
     *
     * @var bool
     */
    protected $hydrateId = false;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param $entity
     * @param array $data
     * @return mixed|object
     * @throws Exception
     */
    public function hydrate($entity, array $data)
    {
        if (is_string($entity) && class_exists($entity)) {
            $entity = new $entity;
        }
        elseif (!is_object($entity)) {
            throw new Exception('Entity passed to ArrayHydrator::hydrate() must be a class name or entity object');
        }

        $entity = $this->hydrateProperties($entity, $data);
        $entity = $this->hydrateAssociations($entity, $data);
        return $entity;
    }

    /**
     * @param boolean $hydrateAssociationReferences
     */
    public function setHydrateAssociationReferences($hydrateAssociationReferences)
    {
        $this->hydrateAssociationReferences = $hydrateAssociationReferences;
    }

    /**
     * @param bool $hydrateId
     */
    public function setHydrateId($hydrateId)
    {
        $this->hydrateId = $hydrateId;
    }

    /**
     * @param int $hydrateBy
     */
    public function setHydrateBy($hydrateBy)
    {
        $this->hydrateBy = $hydrateBy;
    }

    /**
     * @param object $entity the doctrine entity
     * @param array $data
     * @return object
     */
    protected function hydrateProperties($entity, $data)
    {
        $reflectionObject = new \ReflectionObject($entity);

        $metaData = $this->entityManager->getClassMetadata(get_class($entity));
        
        $platform = $this->entityManager->getConnection()
                                        ->getDatabasePlatform();

        $skipFields = $this->hydrateId ? [] : $metaData->identifier;

        foreach ($metaData->fieldNames as $fieldName) {
            $dataKey = $this->hydrateBy === self::HYDRATE_BY_FIELD ? $fieldName : $metaData->getColumnName($fieldName);

            if (array_key_exists($dataKey, $data) && !in_array($fieldName, $skipFields, true)) {
                $value = $data[$dataKey];

                if ($fieldType = $metaData->fieldMappings[$fieldName]->type ?? null) {
                    $type = Type::getType($fieldType);
                    $value = $type->convertToPHPValue($value, $platform);
                }

                $entity = $this->setProperty($entity, $fieldName, $value, $reflectionObject);
            }
        }

        return $entity;
    }

    /**
     * @param $entity
     * @param $data
     * @return mixed
     */
    protected function hydrateAssociations($entity, $data)
    {
        $metaData = $this->entityManager->getClassMetadata(get_class($entity));
        foreach ($metaData->associationMappings as $fieldName => $mapping) {
            $associationData = $this->getAssociatedId($fieldName, $mapping, $data);
            if (!empty($associationData)) {
                if (in_array($mapping['type'], [ClassMetadataInfo::ONE_TO_ONE, ClassMetadataInfo::MANY_TO_ONE])) {
                    $entity = $this->hydrateToOneAssociation($entity, $fieldName, $mapping, $associationData);
                }

                if (in_array($mapping['type'], [ClassMetadataInfo::ONE_TO_MANY, ClassMetadataInfo::MANY_TO_MANY])) {
                    $entity = $this->hydrateToManyAssociation($entity, $fieldName, $mapping, $associationData);
                }
            }
        }

        return $entity;
    }

    /**
     * Retrieves the associated entity's id from $data
     *
     * @param string $fieldName name of field that stores the associated entity
     * @param array $mapping doctrine's association mapping array for the field
     * @param array $data the hydration data
     *
     * @return mixed null, if the association is not found
     */
    protected function getAssociatedId($fieldName, $mapping, $data)
    {
        if ($this->hydrateBy === self::HYDRATE_BY_FIELD) {

            return isset($data[$fieldName]) ? $data[$fieldName] : null;
        }

        // from this point it is self::HYDRATE_BY_COLUMN
        // we do not support compound foreign keys (yet)
        if (isset($mapping['joinColumns']) && count($mapping['joinColumns']) === 1) {
            $columnName = $mapping['joinColumns'][0]['name'];

            return isset($data[$columnName]) ? $data[$columnName] : null;
        }

        // If joinColumns does not exist, then this is not the owning side of an association
        // This should not happen with column based hydration
        return null;
    }

    /**
     * @param $entity
     * @param $propertyName
     * @param $mapping
     * @param $value
     * @return mixed
     */
    protected function hydrateToOneAssociation($entity, $propertyName, $mapping, $value)
    {
        $reflectionObject = new \ReflectionObject($entity);

        $toOneAssociationObject = $this->fetchAssociationEntity($mapping['targetEntity'], $value);
        if (!is_null($toOneAssociationObject)) {
            $entity = $this->setProperty($entity, $propertyName, $toOneAssociationObject, $reflectionObject);
        }

        return $entity;
    }

    /**
     * @param $entity
     * @param $propertyName
     * @param $mapping
     * @param $value
     * @return mixed
     */
    protected function hydrateToManyAssociation($entity, $propertyName, $mapping, $value)
    {
        $reflectionObject = new \ReflectionObject($entity);
        $values = is_array($value) ? $value : [$value];

        $assocationObjects = [];
        foreach ($values as $value) {
            if (is_array($value)) {
                $assocationObjects[] = $this->hydrate($mapping['targetEntity'], $value);
            }
            elseif ($associationObject = $this->fetchAssociationEntity($mapping['targetEntity'], $value)) {
                $assocationObjects[] = $associationObject;
            }
        }

        $entity = $this->setProperty($entity, $propertyName, $assocationObjects, $reflectionObject);

        return $entity;
    }

    /**
     * @param $entity
     * @param $propertyName
     * @param $value
     * @param null $reflectionObject
     * @return mixed
     */
    protected function setProperty($entity, $propertyName, $value, $reflectionObject = null)
    {
        $reflectionObject = is_null($reflectionObject) ? new \ReflectionObject($entity) : $reflectionObject;
        $property = $reflectionObject->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($entity, $value);
        return $entity;
    }

    /**
     * @param $className
     * @param $id
     * @return bool|\Doctrine\Common\Proxy\Proxy|null|object
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function fetchAssociationEntity($className, $id)
    {
        if ($this->hydrateAssociationReferences) {
            return $this->entityManager->getReference($className, $id);
        }

        return $this->entityManager->find($className, $id);
    }
}
