<?php

declare(strict_types=1);

namespace YaPro\DoctrineExt\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

#[ORM\Entity()]
class Article
{
    #[Groups(['publicGroup'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    protected ?int $id = null; // ?int чтобы doctrine не падал при удалении записи

    #[ORM\Column]
    #[Groups(['publicGroup'])]
    private int $parentId = 0;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['publicGroup'])]
    private string $title = '';

    /**
     * @var Collection|ArrayCollection|Comment[]
     */
    #[MaxDepth(1)]
    #[Groups(['publicGroup'])]
    #[ORM\OneToMany(mappedBy: 'article', targetEntity: Comment::class, cascade: ['persist'], orphanRemoval: false)]
    private Collection $comments;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
    }

    public function getId(): ?int
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

        // if ($updateRelation) {
        //    $comment->setArticle(null, false);
        // }
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
