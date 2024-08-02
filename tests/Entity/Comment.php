<?php

declare(strict_types=1);

namespace YaPro\DoctrineExt\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 */
class Comment
{
	/**
	 * @var ?int
	 *
	 * @ORM\Id
	 * @ORM\Column(type="integer")
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
    #[Groups(['publicGroup'])]
	private ?int $id = 0; // ?int чтобы doctrine не падал при удалении записи

    /**
     * @ORM\Column(type="integer")
     */
    #[Groups(['publicGroup'])]
    private int $parentId = 0;

	/**
	 * @ORM\Column(type="text")
	 */
    #[Groups(['publicGroup'])]
	private string $message;

    /**
     * @ORM\ManyToOne(targetEntity="Article", inversedBy="comments")
     * @ORM\JoinColumn(name="articleId", nullable=false, onDelete="RESTRICT")
     */
    private Article $article;

	public function getId(): int
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
