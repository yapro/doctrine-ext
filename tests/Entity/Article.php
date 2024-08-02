<?php

declare(strict_types=1);

namespace YaPro\DoctrineExt\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 */
class Article
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
     * @ORM\Column(type="string", length=255, unique=true)
     */
    #[Groups(['publicGroup'])]
    private string $title = '';

    /**
     * @var Collection|Comment[]
     * @ORM\OneToMany(targetEntity="Comment", mappedBy="article", cascade={"persist"}, orphanRemoval=false)
     */
    #[MaxDepth(1)]
    #[Groups(['publicGroup'])]
    private Collection $comments;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
    }

	public function getId(): int
	{
		return $this->id;
	}

	public function getParentId(): int
	{
		return $this->parentId;
	}

	public function setParentId(int $parentId): self
	{
		$this->parentId = $parentId;
		return $this;
	}

    public function addComment(Comment $comment, bool $updateRelation = true): self
    {
        if ($this->comments->contains($comment)) {
            return $this;
        }
        $this->comments->add($comment);
        if ($updateRelation) {
            $comment->setArticle($this, false);
        }
        return $this;
    }

    public function removeComment(Comment $comment, bool $updateRelation = true): self
    {
        $this->comments->removeElement($comment);
        //if ($updateRelation) {
        //    $comment->setArticle(null, false);
        //}
        return $this;
    }

    /**
     * @return Collection|Comment[]
     */
    public function getComments(): iterable
    {
        return $this->comments;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }
}
