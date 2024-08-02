<?php

declare(strict_types=1);

namespace YaPro\DoctrineExt\EntityExtension;

use Doctrine\ORM\Mapping as ORM;
use YaPro\DoctrineExt\Enum\EntityFieldValueEnum;

/**
 * Решено использовать трейт, потому что анотация MappedSuperclass в абстрактном классе вызывает ошибку:
 * No identifier/primary key specified for Entity "B" sub class of "A". Every Entity must have an identifier/primary key.
 *
 * @see https://www.doctrine-project.org/projects/doctrine-orm/en/2.7/reference/inheritance-mapping.html
 */
trait RequiredFieldsTrait
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="integer")
     *
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private ?int $id = EntityFieldValueEnum::DEFAULT_PRIMARY_KEY_NUMBER; // ?int чтобы doctrine не падал при удалении записи

    /**
     * Поле заполняется автоматически перед onFlush.
     *
     * @ORM\Column(type="datetime", nullable=false, options={"default": "CURRENT_TIMESTAMP"})
     */
    private ?\DateTimeInterface $createdAt = null;

    /**
     * Поле заполняется автоматически перед onFlush.
     *
     * @ORM\Column(type="datetime", nullable=false, options={"default": "CURRENT_TIMESTAMP"})
     */
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setCreatedAt(\DateTimeInterface $dateTime): self
    {
        $this->createdAt = $dateTime;

        return $this;
    }

    public function setUpdatedAt(\DateTimeInterface $dateTime): self
    {
        $this->updatedAt = $dateTime;

        return $this;
    }
}
