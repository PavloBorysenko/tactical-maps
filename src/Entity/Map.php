<?php

namespace App\Entity;

use App\Repository\MapRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MapRepository::class)]
#[ORM\Table(name: 'maps')]
class Map
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'float')]
    #[Assert\NotBlank]
    private float $centerLat = 0;

    #[ORM\Column(type: 'float')]
    #[Assert\NotBlank]
    private float $centerLng = 0;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank]
    #[Assert\Range(min: 1, max: 20)]
    private int $zoomLevel = 12;

    #[ORM\OneToMany(mappedBy: 'map', targetEntity: GeoObject::class, orphanRemoval: true)]
    private Collection $geoObjects;

    public function __construct()
    {
        $this->geoObjects = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCenterLat(): float
    {
        return $this->centerLat;
    }

    public function setCenterLat(float $centerLat): static
    {
        $this->centerLat = $centerLat;

        return $this;
    }

    public function getCenterLng(): float
    {
        return $this->centerLng;
    }

    public function setCenterLng(float $centerLng): static
    {
        $this->centerLng = $centerLng;

        return $this;
    }

    public function getZoomLevel(): int
    {
        return $this->zoomLevel;
    }

    public function setZoomLevel(int $zoomLevel): static
    {
        $this->zoomLevel = $zoomLevel;

        return $this;
    }

    /**
     * @return Collection<int, GeoObject>
     */
    public function getGeoObjects(): Collection
    {
        return $this->geoObjects;
    }

    public function addGeoObject(GeoObject $geoObject): static
    {
        if (!$this->geoObjects->contains($geoObject)) {
            $this->geoObjects->add($geoObject);
            $geoObject->setMap($this);
        }

        return $this;
    }

    public function removeGeoObject(GeoObject $geoObject): static
    {
        if ($this->geoObjects->removeElement($geoObject)) {
            // set the owning side to null (unless already changed)
            if ($geoObject->getMap() === $this) {
                $geoObject->setMap(null);
            }
        }

        return $this;
    }
    
    /**
     * Returns a representation of this Map for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'centerLat' => $this->centerLat,
            'centerLng' => $this->centerLng,
            'zoomLevel' => $this->zoomLevel
        ];
    }
    
    public function __toString(): string
    {
        return $this->title ?? 'Unnamed Map';
    }
} 