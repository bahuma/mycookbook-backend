<?php

namespace App\Entity;

use App\ApiEntityInterface;
use App\Repository\RecipeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=RecipeRepository::class)
 */
class Recipe implements ApiEntityInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=512)
     */
    private $title;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $schemaorg = [];

    /**
     * @ORM\ManyToOne(targetEntity=Cookbook::class, inversedBy="recipes")
     * @ORM\JoinColumn(nullable=false)
     */
    private $cookbook;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $image;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $source;

    /**
     * {@inheritDoc}
     */
    public function getApiFields(): array
    {

        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'cookbook' => $this->getCookbook()->getId(),
            'source' => $this->getSource(),
            'image' => $this->getImage(),
            'schemaorg' => $this->getSchemaorg(),
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getSchemaorg(): ?array
    {
        return $this->schemaorg;
    }

    public function setSchemaorg(?array $schemaorg): self
    {
        $this->schemaorg = $schemaorg;

        return $this;
    }

    public function getCookbook(): ?Cookbook
    {
        return $this->cookbook;
    }

    public function setCookbook(?Cookbook $cookbook): self
    {
        $this->cookbook = $cookbook;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;

        return $this;
    }
}
