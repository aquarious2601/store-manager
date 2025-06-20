<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $kcCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $eansCode = null;

    #[ORM\Column(type: 'text')]
    private ?string $name = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKcCode(): ?string
    {
        return $this->kcCode;
    }

    public function setKcCode(string $kcCode): static
    {
        $this->kcCode = $kcCode;

        return $this;
    }

    public function getEansCode(): ?string
    {
        return $this->eansCode;
    }

    public function setEansCode(?string $eansCode): static
    {
        $this->eansCode = $eansCode;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
