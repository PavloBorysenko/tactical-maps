<?php

namespace App\Repository;

use App\Entity\GeoObject;
use App\Entity\Map;
use App\Entity\Side;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Location\Coordinate;
use Location\Polygon as GeoPolygon;
use Location\Distance\Vincenty;
use Location\Distance\Haversine;

/**
 * Repository for GeoObject entity with GeoJSON support
 * Integration with phpgeo library for spatial calculations
 * 
 * @extends ServiceEntityRepository<GeoObject>
 */
class GeoObjectRepository extends ServiceEntityRepository
{
    /**
     * @var Vincenty|Haversine Distance calculator implementation from phpgeo
     */
    private $distanceCalculator;
    
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GeoObject::class);
        
        // Default to Vincenty algorithm for precise distance calculations
        $this->distanceCalculator = new Vincenty();
    }

    /**
     * Set the distance calculator to use in spatial operations
     * 
     * @param Vincenty|Haversine $calculator The phpgeo distance calculator
     * @return self
     */
    public function setDistanceCalculator($calculator): self
    {
        $this->distanceCalculator = $calculator;
        return $this;
    }    

    /**
     * Save a geo object
     */
    public function save(GeoObject $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove a geo object
     */
    public function remove(GeoObject $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find a geo object by its hash
     * 
     * @param string $hash The unique hash to search for
     * @return GeoObject|null The found object or null if not found
     */
    public function findOneByHash(string $hash): ?GeoObject
    {
        return $this->findOneBy(['hash' => $hash]);
    }

    /**
     * Find all geo objects visible to the given side
     */
    public function findVisibleForSide(?Side $side = null): array
    {
        $qb = $this->createQueryBuilder('g');
        
        $this->addVisibilityConstraint($qb, $side);
        
        return $qb->getQuery()->getResult();
    }
    
    /**
     * Find all geo objects on the given map visible to the given side
     */
    public function findVisibleByMap(Map $map, ?Side $side = null): array
    {
        $qb = $this->createQueryBuilder('g')
                  ->where('g.map = :map')
                  ->setParameter('map', $map);
        
        $this->addVisibilityConstraint($qb, $side);
        
        return $qb->getQuery()->getResult();
    }
    
    /**
     * Find all points on the given map visible to the given side
     */
    public function findVisiblePointsByMap(Map $map, ?Side $side = null): array
    {
        $qb = $this->createQueryBuilder('g')
                  ->where('g.map = :map')
                  ->andWhere('g.geometryType = :type')
                  ->setParameter('map', $map)
                  ->setParameter('type', GeoObject::GEOM_TYPE_POINT);
        
        $this->addVisibilityConstraint($qb, $side);
        
        return $qb->getQuery()->getResult();
    }
    
    /**
     * Find all polygons on the given map visible to the given side
     */
    public function findVisiblePolygonsByMap(Map $map, ?Side $side = null): array
    {
        $qb = $this->createQueryBuilder('g')
                  ->where('g.map = :map')
                  ->andWhere('g.geometryType = :type')
                  ->setParameter('map', $map)
                  ->setParameter('type', GeoObject::GEOM_TYPE_POLYGON);
        
        $this->addVisibilityConstraint($qb, $side);
        
        return $qb->getQuery()->getResult();
    }
    
    /**
     * Find all circles on the given map visible to the given side
     */
    public function findVisibleCirclesByMap(Map $map, ?Side $side = null): array
    {
        $qb = $this->createQueryBuilder('g')
                  ->where('g.map = :map')
                  ->andWhere('g.geometryType = :type')
                  ->setParameter('map', $map)
                  ->setParameter('type', GeoObject::GEOM_TYPE_CIRCLE);
        
        $this->addVisibilityConstraint($qb, $side);
        
        return $qb->getQuery()->getResult();
    }
    
    /**
     * Find all geo objects owned by the given side and visible to the viewer side
     */
    public function findVisibleBySide(Side $ownerSide, ?Side $viewerSide = null): array
    {
        $qb = $this->createQueryBuilder('g')
                  ->where('g.side = :side')
                  ->setParameter('side', $ownerSide);
        
        $this->addVisibilityConstraint($qb, $viewerSide);
        
        return $qb->getQuery()->getResult();
    }
    
    /**
     * Find points within radius from the given coordinates (in kilometers)
     * Uses phpgeo library for precise calculations
     */
    public function findPointsWithinRadius(float $latitude, float $longitude, float $radiusKm, ?Map $map = null, ?Side $side = null): array
    {
        // Create a phpgeo Coordinate object for the center point
        $center = new Coordinate($latitude, $longitude);
        
        // First, get all points and circles (we'll filter them later)
        $qb = $this->createQueryBuilder('g')
            ->where('g.geometryType IN (:types)')
            ->setParameter('types', [GeoObject::GEOM_TYPE_POINT, GeoObject::GEOM_TYPE_CIRCLE]);
            
        if ($map) {
            $qb->andWhere('g.map = :map')
               ->setParameter('map', $map);
        }
        
        $this->addVisibilityConstraint($qb, $side);
        
        $objects = $qb->getQuery()->getResult();
        
        // Filter objects by distance using phpgeo
        return array_filter($objects, function(GeoObject $obj) use ($center, $radiusKm) {
            if ($obj->getGeometryType() === GeoObject::GEOM_TYPE_POINT) {
                // For points, check the distance from center
                $point = new Coordinate($obj->getLatitude(), $obj->getLongitude());
                $distance = $this->distanceCalculator->getDistance($center, $point) / 1000; // Convert to km
                
                return $distance <= $radiusKm;
            } elseif ($obj->getGeometryType() === GeoObject::GEOM_TYPE_CIRCLE) {
                // For circles, check if they intersect with our search circle
                $circleCenter = new Coordinate($obj->getLatitude(), $obj->getLongitude());
                $circleRadius = $obj->getRadius() / 1000; // Convert to km
                
                // Calculate distance between circle centers
                $centerDistance = $this->distanceCalculator->getDistance($center, $circleCenter) / 1000; // Convert to km
                
                // Circles intersect if the distance between their centers is less than the sum of their radii
                return $centerDistance <= ($radiusKm + $circleRadius);
            }
            
            return false;
        });
    }
    
    /**
     * Find points that are inside the given polygon using phpgeo
     */
    public function findPointsInPolygon(GeoObject $polygon, ?Map $map = null, ?Side $side = null): array
    {
        if ($polygon->getGeometryType() !== GeoObject::GEOM_TYPE_POLYGON) {
            throw new \InvalidArgumentException('The object must be a polygon');
        }
        
        // Create a phpgeo Polygon object
        $phpgeoPolygon = new GeoPolygon();
        $coordinates = $polygon->getPolygonCoordinates();
        
        foreach ($coordinates as $point) {
            // GeoJSON format is [longitude, latitude]
            $phpgeoPolygon->addPoint(new Coordinate($point[1], $point[0]));
        }
        
        // Get all points to check
        $qb = $this->createQueryBuilder('g')
            ->where('g.geometryType = :type')
            ->setParameter('type', GeoObject::GEOM_TYPE_POINT);
            
        if ($map) {
            $qb->andWhere('g.map = :map')
               ->setParameter('map', $map);
        }
        
        $this->addVisibilityConstraint($qb, $side);
        
        $points = $qb->getQuery()->getResult();
        
        // Filter points using phpgeo's contains method
        return array_filter($points, function(GeoObject $point) use ($phpgeoPolygon) {
            $coordinate = new Coordinate($point->getLatitude(), $point->getLongitude());
            return $phpgeoPolygon->contains($coordinate);
        });
    }
    
    /**
     * Calculate the distance between two GeoObjects using phpgeo
     * 
     * @param GeoObject $object1 First object (must be Point or Circle)
     * @param GeoObject $object2 Second object (must be Point or Circle)
     * @return float Distance in meters
     */
    public function calculateDistance(GeoObject $object1, GeoObject $object2): float
    {
        if (!in_array($object1->getGeometryType(), [GeoObject::GEOM_TYPE_POINT, GeoObject::GEOM_TYPE_CIRCLE]) ||
            !in_array($object2->getGeometryType(), [GeoObject::GEOM_TYPE_POINT, GeoObject::GEOM_TYPE_CIRCLE])) {
            throw new \InvalidArgumentException('Both objects must be points or circles');
        }
        
        $point1 = new Coordinate($object1->getLatitude(), $object1->getLongitude());
        $point2 = new Coordinate($object2->getLatitude(), $object2->getLongitude());
        
        return $this->distanceCalculator->getDistance($point1, $point2);
    }
    
    /**
     * Calculate the bearing angle between two GeoObjects using phpgeo
     * 
     * @param GeoObject $from Starting object (must be Point or Circle)
     * @param GeoObject $to Target object (must be Point or Circle)
     * @return float Bearing in degrees (0-360)
     */
    public function calculateBearing(GeoObject $from, GeoObject $to): float
    {
        if (!in_array($from->getGeometryType(), [GeoObject::GEOM_TYPE_POINT, GeoObject::GEOM_TYPE_CIRCLE]) ||
            !in_array($to->getGeometryType(), [GeoObject::GEOM_TYPE_POINT, GeoObject::GEOM_TYPE_CIRCLE])) {
            throw new \InvalidArgumentException('Both objects must be points or circles');
        }
        
        $point1 = new Coordinate($from->getLatitude(), $from->getLongitude());
        $point2 = new Coordinate($to->getLatitude(), $to->getLongitude());
        
        // Calculate bearing using phpgeo (requires Location\Bearing class)
        $bearingCalculator = new \Location\Bearing\BearingSpherical();
        return $bearingCalculator->calculateBearing($point1, $point2);
    }
    
    /**
     * Check if a point is inside a polygon using phpgeo
     * 
     * @param GeoObject $point The point to check (must be Point)
     * @param GeoObject $polygon The polygon to check against (must be Polygon)
     * @return bool True if the point is inside the polygon
     */
    public function isPointInPolygon(GeoObject $point, GeoObject $polygon): bool
    {
        if ($point->getGeometryType() !== GeoObject::GEOM_TYPE_POINT) {
            throw new \InvalidArgumentException('First argument must be a point');
        }
        
        if ($polygon->getGeometryType() !== GeoObject::GEOM_TYPE_POLYGON) {
            throw new \InvalidArgumentException('Second argument must be a polygon');
        }
        
        $coordinate = new Coordinate($point->getLatitude(), $point->getLongitude());
        
        $phpgeoPolygon = new GeoPolygon();
        $coordinates = $polygon->getPolygonCoordinates();
        
        foreach ($coordinates as $polygonPoint) {
            // GeoJSON format is [longitude, latitude]
            $phpgeoPolygon->addPoint(new Coordinate($polygonPoint[1], $polygonPoint[0]));
        }
        
        return $phpgeoPolygon->contains($coordinate);
    }
    
    /**
     * Get all geo objects as a GeoJSON FeatureCollection
     */
    public function getGeoJsonFeatureCollection(array $geoObjects): array
    {
        $features = [];
        
        foreach ($geoObjects as $geoObject) {
            $features[] = $geoObject->toGeoJsonFeature();
        }
        
        return [
            'type' => 'FeatureCollection',
            'features' => $features
        ];
    }
    
    /**
     * Search for geo objects by name or description
     */
    public function searchByText(string $query, ?Map $map = null, ?Side $side = null): array
    {
        $qb = $this->createQueryBuilder('g')
            ->where('g.name LIKE :query')
            ->orWhere('g.description LIKE :query')
            ->setParameter('query', '%' . $query . '%');
        
        if ($map) {
            $qb->andWhere('g.map = :map')
               ->setParameter('map', $map);
        }
        
        $this->addVisibilityConstraint($qb, $side);
        
        return $qb->getQuery()->getResult();
    }
    
    /**
     * Find objects with expired TTL
     */
    public function findExpiredObjects(): array
    {
        $now = new \DateTime();
        
        return $this->createQueryBuilder('g')
            ->where('g.ttl IS NOT NULL AND g.ttl > 0')
            ->andWhere('g.createdAt + g.ttl < :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find all geo objects in a specific bounding box (viewport)
     */
    public function findInBoundingBox(
        float $minLat, 
        float $minLng, 
        float $maxLat, 
        float $maxLng, 
        ?Map $map = null, 
        ?Side $side = null
    ): array {
        $qb = $this->createQueryBuilder('g')
            ->where(
                // For points and circles
                '(g.geometryType IN (:pointTypes) AND ' .
                'JSON_UNQUOTE(JSON_EXTRACT(g.geometry, \'$.coordinates[1]\')) BETWEEN :minLat AND :maxLat AND ' .
                'JSON_UNQUOTE(JSON_EXTRACT(g.geometry, \'$.coordinates[0]\')) BETWEEN :minLng AND :maxLng)'
            )
            ->setParameter('pointTypes', [GeoObject::GEOM_TYPE_POINT, GeoObject::GEOM_TYPE_CIRCLE])
            ->setParameter('minLat', $minLat)
            ->setParameter('maxLat', $maxLat)
            ->setParameter('minLng', $minLng)
            ->setParameter('maxLng', $maxLng);
        
        // For polygons, we need a more complex query, but for simplicity,
        // we'll just fetch all polygons and filter them in PHP
        // In a production environment, this should be done with proper spatial queries
        
        if ($map) {
            $qb->andWhere('g.map = :map')
               ->setParameter('map', $map);
        }
        
        $this->addVisibilityConstraint($qb, $side);
        
        $result = $qb->getQuery()->getResult();
        
        // Now get polygons that might intersect with this bounding box
        $polygonQb = $this->createQueryBuilder('g')
            ->where('g.geometryType = :polygonType')
            ->setParameter('polygonType', GeoObject::GEOM_TYPE_POLYGON);
            
        if ($map) {
            $polygonQb->andWhere('g.map = :map')
                    ->setParameter('map', $map);
        }
        
        $this->addVisibilityConstraint($polygonQb, $side);
        
        $polygons = $polygonQb->getQuery()->getResult();
        
        // Filter polygons by checking if they intersect with the bounding box
        foreach ($polygons as $polygon) {
            $coordinates = $polygon->getPolygonCoordinates();
            
            // Simple check: if any point of the polygon is in the bounding box,
            // or if the polygon completely contains the bounding box
            $isInBoundingBox = false;
            
            foreach ($coordinates as $point) {
                $lng = $point[0];
                $lat = $point[1];
                
                if ($lat >= $minLat && $lat <= $maxLat && $lng >= $minLng && $lng <= $maxLng) {
                    $isInBoundingBox = true;
                    break;
                }
            }
            
            if ($isInBoundingBox) {
                $result[] = $polygon;
            }
        }
        
        return $result;
    }
    
    /**
     * Add visibility constraint to the query based on the side
     */
    private function addVisibilityConstraint(QueryBuilder $qb, ?Side $side = null): void
    {
        if ($side === null) {
            // For unauthenticated users, show only objects visible to all
            $qb->andWhere('g.visibleToSides IS NULL');
        } else {
            // For authenticated side, show what's visible to all OR specifically to this side
            $sideId = $side->getId();
            
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNull('g.visibleToSides'),
                    'JSON_CONTAINS(g.visibleToSides, :sideId) = 1'
                )
            )
            ->setParameter('sideId', json_encode($sideId));
        }
    }
    
    /**
     * Create a collection of GeoObjects from a GeoJSON FeatureCollection
     * 
     * @param array $featureCollection GeoJSON FeatureCollection
     * @param Map $map Map to associate with created objects
     * @param Side|null $side Side to associate with created objects
     * @return array<GeoObject> Array of created GeoObject entities
     */
    public function createFromGeoJson(array $featureCollection, Map $map, ?Side $side = null): array
    {
        if (!isset($featureCollection['type']) || $featureCollection['type'] !== 'FeatureCollection' || !isset($featureCollection['features'])) {
            throw new \InvalidArgumentException('Invalid GeoJSON FeatureCollection format');
        }
        
        $result = [];
        
        foreach ($featureCollection['features'] as $feature) {
            if (!isset($feature['type']) || $feature['type'] !== 'Feature' || !isset($feature['geometry'])) {
                continue; // Skip invalid features
            }
            
            $geometry = $feature['geometry'];
            $properties = $feature['properties'] ?? [];
            
            $name = $properties['name'] ?? 'Unnamed object';
            $description = $properties['description'] ?? null;
            
            try {
                $geoObject = null;
                
                switch ($geometry['type']) {
                    case 'Point':
                        // Check if it's actually a circle (has radius property)
                        if (isset($properties['radius']) && is_numeric($properties['radius'])) {
                            $longitude = $geometry['coordinates'][0];
                            $latitude = $geometry['coordinates'][1];
                            $radius = (float) $properties['radius'];
                            
                            $geoObject = GeoObject::createCircle($name, $latitude, $longitude, $radius);
                        } else {
                            $longitude = $geometry['coordinates'][0];
                            $latitude = $geometry['coordinates'][1];
                            
                            $geoObject = GeoObject::createPoint($name, $latitude, $longitude);
                        }
                        break;
                        
                    case 'Polygon':
                        $coordinates = $geometry['coordinates'][0] ?? []; // First ring (outer)
                        
                        $geoObject = GeoObject::createPolygon($name, $coordinates);
                        break;
                        
                    default:
                        // Unsupported geometry type
                        continue;
                }
                
                if ($geoObject) {
                    $geoObject->setDescription($description);
                    $geoObject->setMap($map);
                    $geoObject->setSide($side);
                    
                    if (isset($properties['iconUrl'])) {
                        $geoObject->setIconUrl($properties['iconUrl']);
                    }
                    
                    if (isset($properties['ttl']) && is_numeric($properties['ttl'])) {
                        $geoObject->setTtl((int) $properties['ttl']);
                    }
                    
                    if (isset($properties['visibleToSides']) && is_array($properties['visibleToSides'])) {
                        $geoObject->setVisibleToSides($properties['visibleToSides']);
                    }
                    
                    $result[] = $geoObject;
                }
            } catch (\Exception $e) {
                // Log error or handle invalid geometry
                continue;
            }
        }
        
        return $result;
    }
    /**
     * Delete all expired objects
     * 
     * @return int Number of deleted objects
     */
    public function deleteExpiredObjects(): int
    {
        $now = new \DateTimeImmutable();
        
        $qb = $this->createQueryBuilder('g')
            ->delete()
            ->where('g.ttl IS NOT NULL AND g.ttl > 0')
            ->andWhere(
                $this->getEntityManager()->getExpressionBuilder()->andX(
                    'g.updatedAt IS NULL',
                    'DATE_ADD(g.createdAt, g.ttl, \'second\') <= :now'
                )
            )
            ->orWhere(
                $this->getEntityManager()->getExpressionBuilder()->andX(
                    'g.updatedAt IS NOT NULL',
                    'DATE_ADD(g.updatedAt, g.ttl, \'second\') <= :now'
                )
            )
            ->setParameter('now', $now);
        
        return $qb->getQuery()->execute();
    }
    /**
     * Find objects that have been updated after a specific timestamp
     * 
     * @param \DateTimeInterface $timestamp The timestamp to compare against
     * @param Map|null $map Optional map to restrict the search to
     * @param Side|null $side Optional side for visibility filtering
     * @return array<GeoObject> Array of geo objects updated after the timestamp
     */
    public function findUpdatedAfter(
        \DateTimeInterface $timestamp, 
        ?Map $map = null, 
        ?Side $side = null
    ): array {
        $qb = $this->createQueryBuilder('g')
            ->where('g.updatedAt > :timestamp OR g.createdAt > :timestamp')
            ->setParameter('timestamp', $timestamp);
        
        if ($map) {
            $qb->andWhere('g.map = :map')
               ->setParameter('map', $map);
        }
        
        $this->addVisibilityConstraint($qb, $side);
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Find objects by multiple hashes
     * 
     * @param array $hashes Array of hash strings to find
     * @param Side|null $side Optional side for visibility filtering
     * @return array<GeoObject> Array of found geo objects
     */
    public function findByHashes(array $hashes, ?Side $side = null): array
    {
        if (empty($hashes)) {
            return [];
        }
        
        $qb = $this->createQueryBuilder('g')
            ->where('g.hash IN (:hashes)')
            ->setParameter('hashes', $hashes);
        
        $this->addVisibilityConstraint($qb, $side);
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Find all objects where TTL has not expired since their last update
     * 
     * @param Side|null $side Optional side for visibility filtering
     * @return array<GeoObject> Array of active (non-expired) geo objects
     */
    public function findAllActive(?Side $side = null): array
    {
        $now = new \DateTimeImmutable();
        
        $qb = $this->createQueryBuilder('g')
            ->andWhere(
                $this->getEntityManager()->getExpressionBuilder()->orX(
                    // Objects without TTL or with TTL = 0 (never expire)
                    'g.ttl IS NULL OR g.ttl = 0',
                    
                    // Objects with TTL > 0 that haven't expired based on createdAt
                    'g.ttl > 0 AND DATE_ADD(g.createdAt, g.ttl, \'second\') > :now',
                    
                    // Objects with TTL > 0 that haven't expired based on updatedAt
                    'g.updatedAt IS NOT NULL AND g.ttl > 0 AND DATE_ADD(g.updatedAt, g.ttl, \'second\') > :now'
                )
            )
            ->setParameter('now', $now);
        
        $this->addVisibilityConstraint($qb, $side);
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Find all objects belonging to a map where TTL has not expired
     * since their last update
     * 
     * @param Map $map The map to search objects for
     * @param Side|null $side Optional side for visibility filtering
     * @return array<GeoObject> Array of active (non-expired) geo objects
     */
    public function findActiveByMap(Map $map, ?Side $side = null): array
    {
        $now = new \DateTimeImmutable();
        
        $qb = $this->createQueryBuilder('g')
            ->where('g.map = :map')
            ->setParameter('map', $map)
            ->andWhere(
                $this->getEntityManager()->getExpressionBuilder()->orX(
                    // Objects without TTL or with TTL = 0 (never expire)
                    'g.ttl IS NULL OR g.ttl = 0',
                    
                    // Objects with TTL > 0 that haven't expired based on createdAt
                    'g.ttl > 0 AND DATE_ADD(g.createdAt, g.ttl, \'second\') > :now',
                    
                    // Objects with TTL > 0 that haven't expired based on updatedAt
                    'g.updatedAt IS NOT NULL AND g.ttl > 0 AND DATE_ADD(g.updatedAt, g.ttl, \'second\') > :now'
                )
            )
            ->setParameter('now', $now);
        
        $this->addVisibilityConstraint($qb, $side);
        
        return $qb->getQuery()->getResult();
    }
 
}