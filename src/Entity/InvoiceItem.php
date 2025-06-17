<?php

namespace App\Entity;

use App\Repository\InvoiceItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: InvoiceItemRepository::class)]
class InvoiceItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $quantity = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $unitPrice = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $total = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Invoice $invoice = null;

    #[ORM\Column(length: 255)]
    private ?string $reference = null;

    #[ORM\Column(length: 255)]
    private ?string $codeEans = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $product = null;

    #[ORM\Column(length: 255)]
    private ?string $taxRate = null;

    #[ORM\OneToMany(mappedBy: 'invoiceItem', targetEntity: SellingItem::class)]
    private Collection $sellingItems;

    public function __construct()
    {
        $this->sellingItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
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

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        $this->invoice = $invoice;
        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;
        return $this;
    }

    public function getCodeEans(): ?string
    {
        return $this->codeEans;
    }

    public function setCodeEans(string $codeEans): static
    {
        $this->codeEans = $codeEans;
        return $this;
    }

    public function getProduct(): ?string
    {
        return $this->product;
    }

    public function setProduct(string $product): static
    {
        $this->product = $product;
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

    /**
     * @return Collection<int, SellingItem>
     */
    public function getSellingItems(): Collection
    {
        return $this->sellingItems;
    }

    public function addSellingItem(SellingItem $sellingItem): static
    {
        if (!$this->sellingItems->contains($sellingItem)) {
            $this->sellingItems->add($sellingItem);
            $sellingItem->setInvoiceItem($this);
        }
        return $this;
    }

    public function removeSellingItem(SellingItem $sellingItem): static
    {
        if ($this->sellingItems->removeElement($sellingItem)) {
            // set the owning side to null (unless already changed)
            if ($sellingItem->getInvoiceItem() === $this) {
                $sellingItem->setInvoiceItem(null);
            }
        }
        return $this;
    }
} 