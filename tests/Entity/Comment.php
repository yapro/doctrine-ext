<?php

declare(strict_types=1);

namespace YaPro\DoctrineExt\Tests\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity()]
class Comment
{
    #[Groups(['publicGroup'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy:"IDENTITY")]
    #[ORM\Column]
    protected ?int $id = null; // ?int чтобы doctrine не падал при удалении записи

    #[Groups(['publicGroup'])]
    #[ORM\Column()]
    private int $parentId = 0;

    #[Groups(['publicGroup'])]
    #[ORM\Column(type: Types::TEXT)]
	private string $message;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(name:"articleId", nullable: false, onDelete: 'RESTRICT')]
    private Article $article;

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getParentId(): int
	{
		return $this->parentId;
	}

	public function setParentId(int $parentId): Comment
	{
		$this->parentId = $parentId;
		return $this;
	}

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

	public function getArticle(): Article
	{
		return $this->article;
	}

	public function setArticle(Article $article = null, bool $updateRelation = true): Comment
	{
		$this->article = $article;
		if ($updateRelation) {
			$article->addComment($this, false);
		}

		return $this;
	}
}
