<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\SideRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entity representing a Side (team/faction) in the mapping service
 * Each Side has a name and color, and can be associated with geographic objects
 */
#[ORM\Entity(repositoryClass: SideRepository::class)]
#[ORM\Table(name: 'sides')]
class Side
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Side name (e.g., "Team Alpha", "Blue Forces", etc.)
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Name cannot be blank")]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: "Name must be at least {{ limit }} characters long",
        maxMessage: "Name cannot be longer than {{ limit }} characters"
    )]
    private ?string $name = null;

    /**
     * Side color in hexadecimal format (e.g., "#FF5733")
     */
    #[ORM\Column(length: 7)]
    #[Assert\NotBlank(message: "Color cannot be blank")]
    #[Assert\Regex(
        pattern: "/^#[0-9A-Fa-f]{6}$/",
        message: "Color must be a valid hex color code (e.g., #FF5733)"
    )]
    private ?string $color = null;

    /**
     * Optional description of the side
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * Collection of GeoObjects that belong to this side
     */
    #[ORM\OneToMany(mappedBy: 'side', targetEntity: GeoObject::class)]
    private Collection $geoObjects;

    public function __construct()
    {
        $this->geoObjects = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        // Ensure color is lowercase for consistency
        $color = strtolower($color);
        
        // If color doesn't start with #, add it
        if (strpos($color, '#') !== 0) {
            $color = '#' . $color;
        }
        
        $this->color = $color;

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
            $geoObject->setSide($this);
        }

        return $this;
    }

    public function removeGeoObject(GeoObject $geoObject): static
    {
        if ($this->geoObjects->removeElement($geoObject)) {
            // set the owning side to null (unless already changed)
            if ($geoObject->getSide() === $this) {
                $geoObject->setSide(null);
            }
        }

        return $this;
    }

    /**
     * Returns a representation of this Side for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'description' => $this->description
        ];
    }

    /**
     * Returns a string representation of this Side
     */
    public function __toString(): string
    {
        return $this->name ?? 'Unnamed Side';
    }
} 