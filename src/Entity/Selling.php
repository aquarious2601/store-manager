<?php

namespace App\Entity;

use App\Repository\SellingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SellingRepository::class)]
class Selling
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $invoiceNumber = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 255)]
    private ?string $paymentMethod = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $amountHT = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $amountTTC = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(length: 255)]
    private ?string $detailsUrl = null;

    #[ORM\ManyToOne(inversedBy: 'sellings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Store $store = null;

    #[ORM\OneToMany(mappedBy: 'selling', targetEntity: SellingItem::class, cascade: ['persist', 'remove'])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(string $invoiceNumber): static
    {
        $this->invoiceNumber = $invoiceNumber;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getAmountHT(): ?string
    {
        return $this->amountHT;
    }

    public function setAmountHT(string $amountHT): static
    {
        $this->amountHT = $amountHT;
        return $this;
    }

    public function getAmountTTC(): ?string
    {
        return $this->amountTTC;
    }

    public function setAmountTTC(string $amountTTC): static
    {
        $this->amountTTC = $amountTTC;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getDetailsUrl(): ?string
    {
        return $this->detailsUrl;
    }

    public function setDetailsUrl(string $detailsUrl): static
    {
        $this->detailsUrl = $detailsUrl;
        return $this;
    }

    public function getStore(): ?Store
    {
        return $this->store;
    }

    public function setStore(?Store $store): static
    {
        $this->store = $store;
        return $this;
    }

    /**
     * @return Collection<int, SellingItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(SellingItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setSelling($this);
        }
        return $this;
    }

    public function removeItem(SellingItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getSelling() === $this) {
                $item->setSelling(null);
            }
        }
        return $this;
    }
} 