<?php

namespace App\Entity;

use App\Repository\SellingItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SellingItemRepository::class)]
class SellingItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $quantity = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $unitPrice = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $total = null;

    #[ORM\Column(length: 255)]
    private ?string $taxRate = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Selling $selling = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Product $productEntity = null;

    /**
     * Calculated price difference between selling price and invoice price
     */
    private ?float $priceDifference = null;

    /**
     * Calculated percentage difference between selling price and invoice price
     */
    private ?float $priceDifferencePercentage = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;
        return $this;
    }

    public function getTaxRate(): ?string
    {
        return $this->taxRate;
    }

    public function setTaxRate(string $taxRate): static
    {
        $this->taxRate = $taxRate;
        return $this;
    }

    public function getSelling(): ?Selling
    {
        return $this->selling;
    }

    public function setSelling(?Selling $selling): static
    {
        $this->selling = $selling;
        return $this;
    }

    public function getProductEntity(): ?Product
    {
        return $this->productEntity;
    }

    public function setProductEntity(?Product $productEntity): static
    {
        $this->productEntity = $productEntity;
        return $this;
    }

    public function getPriceDifference(): ?float
    {
        return $this->priceDifference;
    }

    public function setPriceDifference(?float $priceDifference): self
    {
        $this->priceDifference = $priceDifference;
        return $this;
    }

    public function getPriceDifferencePercentage(): ?float
    {
        return $this->priceDifferencePercentage;
    }

    public function setPriceDifferencePercentage(?float $priceDifferencePercentage): self
    {
        $this->priceDifferencePercentage = $priceDifferencePercentage;
        return $this;
    }
} 