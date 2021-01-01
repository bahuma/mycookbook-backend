<?php

namespace App\Entity;

use App\Repository\RecipeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=RecipeRepository::class)
 */
class Recipe
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
}
