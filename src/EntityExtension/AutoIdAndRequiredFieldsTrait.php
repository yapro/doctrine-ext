<?php

declare(strict_types=1);

namespace YaPro\DoctrineExt\EntityExtension;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use YaPro\DoctrineExt\Enum\EntityFieldValueEnum;

trait AutoIdAndRequiredFieldsTrait
{
    use RequiredFieldsTrait;

    /**
     * Переопределяем id из RequiredFieldsTrait чтобы установить GeneratedValue strategy в IDENTITY.
     *
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @Groups({"apiRead"})
     */
    private ?int $id = EntityFieldValueEnum::DEFAULT_PRIMARY_KEY_NUMBER; // ?int чтобы doctrine не падал при удалении записи
}
