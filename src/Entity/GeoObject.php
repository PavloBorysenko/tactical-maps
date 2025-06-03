<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\GeoObjectRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Geographic object entity that uses GeoJSON for geometry representation.
 * Supports Point, Polygon, and Circle (as Point with radius property).
 * Includes visibility settings based on Side IDs.
 */
#[ORM\Entity(repositoryClass: GeoObjectRepository::class)]
#[ORM\Table(name: 'geo_objects')]
#[ORM\HasLifecycleCallbacks]
class GeoObject
{
    // Standard GeoJSON geometry types
    public const GEOM_TYPE_POINT = 'Point';
    public const GEOM_TYPE_POLYGON = 'Polygon';
    public const GEOM_TYPE_LINESTRING = 'Line';
    
    // Custom geometry type (not standard GeoJSON)
    public const GEOM_TYPE_CIRCLE = 'Circle';
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?int $ttl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $iconUrl = null;

    /**
     * GeoJSON geometry type: Point, Polygon, Line or Circle (custom extension)
     */
    #[ORM\Column(length: 30)]
    #[Assert\Choice(choices: [self::GEOM_TYPE_POINT, self::GEOM_TYPE_POLYGON, self::GEOM_TYPE_LINESTRING, self::GEOM_TYPE_CIRCLE])]
    private string $geometryType = self::GEOM_TYPE_POINT;

    /**
     * GeoJSON-compatible geometry data
     * For points: {"coordinates": [longitude, latitude]}
     * For polygons: {"coordinates": [[[lon1, lat1], [lon2, lat2], ... [lon1, lat1]]]}
     * For circles: {"coordinates": [longitude, latitude], "radius": radiusInMeters}
     */
    #[ORM\Column(type: 'json')]
    private array $geometry = [];

    /**
     * Array of Side IDs that can see this object
     * null means visible to all sides
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $visibleToSides = null;

    /**
     * Unique hash to identify the geo object
     */
    #[ORM\Column(length: 32)]
    private ?string $hash = null;

    #[ORM\ManyToOne(inversedBy: 'geoObjects')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Map $map = null;

    #[ORM\ManyToOne]
    private ?Side $side = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        
        // Generate hash if not set
        if ($this->hash === null) {
            $this->generateHash();
        }
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Generates a unique hash for this geo object
     */
    private function generateHash(): void
    {
        $data = $this->geometryType . '_' . $this->name . '_' . time();
        $this->hash = md5($data);
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getTtl(): ?int
    {
        return $this->ttl;
    }

    public function setTtl(?int $ttl): static
    {
        $this->ttl = $ttl;

        return $this;
    }

    public function getIconUrl(): ?string
    {
        return $this->iconUrl;
    }

    public function setIconUrl(?string $iconUrl): static
    {
        $this->iconUrl = $iconUrl;

        return $this;
    }

    public function getGeometryType(): string
    {
        return $this->geometryType;
    }

    public function setGeometryType(string $geometryType): static
    {
        if (!in_array($geometryType, [self::GEOM_TYPE_POINT, self::GEOM_TYPE_POLYGON, self::GEOM_TYPE_LINESTRING, self::GEOM_TYPE_CIRCLE])) {
            throw new \InvalidArgumentException("Invalid geometry type: $geometryType");
        }
        
        $this->geometryType = $geometryType;

        return $this;
    }

    public function getGeometry(): array
    {
        return $this->geometry;
    }

    /**
     * Sets geometry according to GeoJSON format
     */
    public function setGeometry(array $geometry): static
    {
        // Validate geometry based on type
        switch($this->geometryType) {
            case self::GEOM_TYPE_POINT:
                $this->validatePointGeometry($geometry);
                break;
            case self::GEOM_TYPE_POLYGON:
                $this->validatePolygonGeometry($geometry);
                break;
            case self::GEOM_TYPE_LINESTRING:
                $this->validateLineStringGeometry($geometry);
                break;
            case self::GEOM_TYPE_CIRCLE:
                $this->validateCircleGeometry($geometry);
                break;
        }
        
        $this->geometry = $geometry;

        return $this;
    }

    /**
     * Validates GeoJSON Point geometry
     * Format: {"coordinates": [longitude, latitude]}
     */
    private function validatePointGeometry(array $geometry): void
    {
        if (!isset($geometry['coordinates']) || !is_array($geometry['coordinates']) || count($geometry['coordinates']) !== 2) {
            throw new \InvalidArgumentException(
                'Point geometry must have "coordinates" array with [longitude, latitude]'
            );
        }
        
        list($longitude, $latitude) = $geometry['coordinates'];
        
        if (!is_numeric($longitude) || !is_numeric($latitude)) {
            throw new \InvalidArgumentException(
                'Coordinates must be numeric values'
            );
        }
        
        if ($latitude < -90 || $latitude > 90) {
            throw new \InvalidArgumentException(
                'Latitude must be between -90 and 90 degrees'
            );
        }
        
        if ($longitude < -180 || $longitude > 180) {
            throw new \InvalidArgumentException(
                'Longitude must be between -180 and 180 degrees'
            );
        }
    }

    /**
     * Validates GeoJSON Polygon geometry
     * Format: {"coordinates": [[[lon1, lat1], [lon2, lat2], ..., [lon1, lat1]]]}
     */
    private function validatePolygonGeometry(array $geometry): void
    {
        if (!isset($geometry['coordinates']) || !is_array($geometry['coordinates']) || empty($geometry['coordinates'])) {
            throw new \InvalidArgumentException(
                'Polygon geometry must have "coordinates" array with a list of coordinate arrays'
            );
        }
        
        // Get the outer ring (first element of coordinates array)
        $ring = $geometry['coordinates'][0] ?? null;
        
        if (!is_array($ring) || count($ring) < 4) { // Min 4 points (3 + 1 for closure)
            throw new \InvalidArgumentException(
                'Polygon must have at least 3 points (4 with closure)'
            );
        }
        
        // First and last coordinates should be the same (closed polygon)
        $first = $ring[0];
        $last = end($ring);
        
        if ($first[0] !== $last[0] || $first[1] !== $last[1]) {
            throw new \InvalidArgumentException(
                'Polygon outer ring must be closed (first and last points must be the same)'
            );
        }
        
        // Validate each coordinate pair
        foreach ($ring as $point) {
            if (!is_array($point) || count($point) !== 2 || 
                !is_numeric($point[0]) || !is_numeric($point[1])) {
                throw new \InvalidArgumentException(
                    'Polygon coordinates must be arrays of [longitude, latitude]'
                );
            }
            
            $longitude = $point[0];
            $latitude = $point[1];
            
            if ($latitude < -90 || $latitude > 90) {
                throw new \InvalidArgumentException(
                    'Latitude must be between -90 and 90 degrees'
                );
            }
            
            if ($longitude < -180 || $longitude > 180) {
                throw new \InvalidArgumentException(
                    'Longitude must be between -180 and 180 degrees'
                );
            }
        }
    }

    /**
     * Validates LineString geometry (standard GeoJSON type)
     * Format: {"coordinates": [[lon1, lat1], [lon2, lat2], ...]}
     */
    private function validateLineStringGeometry(array $geometry): void
    {
        if (!isset($geometry['coordinates']) || !is_array($geometry['coordinates']) || count($geometry['coordinates']) < 2) {
            throw new \InvalidArgumentException(
                'LineString geometry must have "coordinates" array with at least 2 points'
            );
        }
        
        foreach ($geometry['coordinates'] as $point) {
            if (!is_array($point) || count($point) !== 2 || 
                !is_numeric($point[0]) || !is_numeric($point[1])) {
                throw new \InvalidArgumentException(
                    'LineString coordinates must be arrays of [longitude, latitude]'
                );
            }
            
            $longitude = $point[0];
            $latitude = $point[1];
            
            if ($latitude < -90 || $latitude > 90) {
                throw new \InvalidArgumentException(
                    'Latitude must be between -90 and 90 degrees'
                );
            }
            
            if ($longitude < -180 || $longitude > 180) {
                throw new \InvalidArgumentException(
                    'Longitude must be between -180 and 180 degrees'
                );
            }
        }
    }

    /**
     * Validates circle geometry (custom extension to GeoJSON)
     * Format: {"coordinates": [longitude, latitude], "radius": radiusInMeters}
     */
    private function validateCircleGeometry(array $geometry): void
    {
        // Check coordinates (same as point)
        $this->validatePointGeometry($geometry);
        
        // Also validate radius
        if (!isset($geometry['radius']) || !is_numeric($geometry['radius']) || $geometry['radius'] <= 0) {
            throw new \InvalidArgumentException(
                'Circle geometry must have a positive numeric "radius" in meters'
            );
        }
    }

    /**
     * Gets array of Side IDs this object is visible to
     * null means visible to all sides
     */
    public function getVisibleToSides(): ?array
    {
        return $this->visibleToSides;
    }

    /**
     * Sets array of Side IDs this object is visible to
     * null means visible to all sides
     */
    public function setVisibleToSides(?array $sideIds): static
    {
        $this->visibleToSides = $sideIds;

        return $this;
    }

    /**
     * Checks if object is visible to the given side
     */
    public function isVisibleToSide(?Side $side = null): bool
    {
        // If visibleToSides is null, object is visible to all
        if ($this->visibleToSides === null) {
            return true;
        }
        
        // If side is null, check if null is explicitly allowed
        if ($side === null) {
            return in_array(null, $this->visibleToSides);
        }
        
        return in_array($side->getId(), $this->visibleToSides);
    }

    /**
     * Makes object visible to the given side
     */
    public function addVisibleToSide(Side $side): static
    {
        // If object is visible to all, keep that behavior
        if ($this->visibleToSides === null) {
            return $this;
        }
        
        $sideId = $side->getId();
        
        if (!in_array($sideId, $this->visibleToSides)) {
            $this->visibleToSides[] = $sideId;
        }

        return $this;
    }

    /**
     * Makes object not visible to the given side
     */
    public function removeVisibleToSide(Side $side): static
    {
        // If object is visible to all, first create a limited list
        if ($this->visibleToSides === null) {
            // In a real scenario, you would get all side IDs except the one being removed
            $this->visibleToSides = [];
        }
        
        $sideId = $side->getId();
        $key = array_search($sideId, $this->visibleToSides);
        
        if ($key !== false) {
            unset($this->visibleToSides[$key]);
            $this->visibleToSides = array_values($this->visibleToSides); // Re-index array
        }

        return $this;
    }

    /**
     * Makes object visible to all sides
     */
    public function setVisibleToAll(): static
    {
        $this->visibleToSides = null;

        return $this;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(string $hash): static
    {
        $this->hash = $hash;

        return $this;
    }

    public function getMap(): ?Map
    {
        return $this->map;
    }

    public function setMap(?Map $map): static
    {
        $this->map = $map;

        return $this;
    }

    public function getSide(): ?Side
    {
        return $this->side;
    }

    public function setSide(?Side $side): static
    {
        $this->side = $side;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
    
    /**
     * Helper method to create a GeoJSON Point
     */
    public static function createPoint(string $name, float $latitude, float $longitude): self
    {
        $point = new self();
        $point->setName($name);
        $point->setGeometryType(self::GEOM_TYPE_POINT);
        $point->setGeometry([
            'coordinates' => [$longitude, $latitude] // Note: GeoJSON uses [longitude, latitude] order
        ]);
        
        return $point;
    }
    
    /**
     * Helper method to create a GeoJSON Polygon
     * @param array $coordinates Array of points in format [[lon1, lat1], [lon2, lat2], ...]
     * The first and last points should be the same to close the polygon
     */
    public static function createPolygon(string $name, array $coordinates): self
    {
        // Ensure polygon is closed (first and last points are the same)
        $firstPoint = $coordinates[0] ?? null;
        $lastPoint = end($coordinates);
        
        if ($firstPoint !== null && ($firstPoint[0] !== $lastPoint[0] || $firstPoint[1] !== $lastPoint[1])) {
            $coordinates[] = $firstPoint; // Close the polygon by adding the first point at the end
        }
        
        $polygon = new self();
        $polygon->setName($name);
        $polygon->setGeometryType(self::GEOM_TYPE_POLYGON);
        $polygon->setGeometry([
            'coordinates' => [$coordinates] // Note: GeoJSON Polygon has an array of linear rings
        ]);
        
        return $polygon;
    }
    
    /**
     * Helper method to create a Circle (custom extension to GeoJSON)
     */
    public static function createCircle(string $name, float $latitude, float $longitude, float $radiusMeters): self
    {
        $circle = new self();
        $circle->setName($name);
        $circle->setGeometryType(self::GEOM_TYPE_CIRCLE);
        $circle->setGeometry([
            'coordinates' => [$longitude, $latitude], // GeoJSON order: longitude, latitude
            'radius' => $radiusMeters
        ]);
        
        return $circle;
    }
    
    /**
     * Helper method to create a GeoJSON LineString
     * @param array $coordinates Array of points in format [[lon1, lat1], [lon2, lat2], ...]
     */
    public static function createLineString(string $name, array $coordinates): self
    {
        $lineString = new self();
        $lineString->setName($name);
        $lineString->setGeometryType(self::GEOM_TYPE_LINESTRING);
        $lineString->setGeometry([
            'coordinates' => $coordinates
        ]);
        
        return $lineString;
    }
    
    /**
     * Gets latitude from Point or Circle geometry
     */
    public function getLatitude(): ?float
    {
        if (!in_array($this->geometryType, [self::GEOM_TYPE_POINT, self::GEOM_TYPE_CIRCLE])) {
            throw new \LogicException('getLatitude() is only available for Point and Circle types');
        }
        
        // GeoJSON coordinates are [longitude, latitude]
        return $this->geometry['coordinates'][1] ?? null;
    }
    
    /**
     * Gets longitude from Point or Circle geometry
     */
    public function getLongitude(): ?float
    {
        if (!in_array($this->geometryType, [self::GEOM_TYPE_POINT, self::GEOM_TYPE_CIRCLE])) {
            throw new \LogicException('getLongitude() is only available for Point and Circle types');
        }
        
        // GeoJSON coordinates are [longitude, latitude]
        return $this->geometry['coordinates'][0] ?? null;
    }
    
    /**
     * Gets radius for Circle geometry (in meters)
     */
    public function getRadius(): ?float
    {
        if ($this->geometryType !== self::GEOM_TYPE_CIRCLE) {
            throw new \LogicException('getRadius() is only available for Circle type');
        }
        
        return $this->geometry['radius'] ?? null;
    }
    
    /**
     * Gets coordinates array for Polygon
     * @return array Array of points in format [[lon1, lat1], [lon2, lat2], ...]
     */
    public function getPolygonCoordinates(): ?array
    {
        if ($this->geometryType !== self::GEOM_TYPE_POLYGON) {
            throw new \LogicException('getPolygonCoordinates() is only available for Polygon type');
        }
        
        return $this->geometry['coordinates'][0] ?? null; // First (outer) ring of the polygon
    }
    
    /**
     * Gets coordinates array for LineString
     * @return array Array of points in format [[lon1, lat1], [lon2, lat2], ...]
     */
    public function getLineStringCoordinates(): ?array
    {
        if ($this->geometryType !== self::GEOM_TYPE_LINESTRING) {
            throw new \LogicException('getLineStringCoordinates() is only available for LineString type');
        }
        
        return $this->geometry['coordinates'] ?? null;
    }
    
    /**
     * Converts this entity to a GeoJSON Feature
     * @return array GeoJSON Feature representation
     */
    public function toGeoJsonFeature(): array
    {
        // Common properties for all geometry types
        $properties = [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'iconUrl' => $this->iconUrl,
            'sideId' => $this->side ? $this->side->getId() : null,
            'hash' => $this->hash,
            'ttl' => $this->ttl,
            'createdAt' => $this->createdAt ? $this->createdAt->format('c') : null,
            'updatedAt' => $this->updatedAt ? $this->updatedAt->format('c') : null
        ];
        
        // For circles, we need special handling since they're not standard GeoJSON
        if ($this->geometryType === self::GEOM_TYPE_CIRCLE) {
            // Option 1: Send as Point with radius property
            $properties['geometryType'] = 'Circle'; // Indicate the real type
            $properties['radius'] = $this->getRadius();
            
            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => $this->geometry['coordinates']
                ],
                'properties' => $properties
            ];
        }
        
        // For standard GeoJSON types (Point, Polygon, LineString)
        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => $this->geometryType,
                'coordinates' => $this->geometry['coordinates']
            ],
            'properties' => $properties
        ];
    }
}