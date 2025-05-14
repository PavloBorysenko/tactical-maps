<?php

namespace App\Service;

use App\Entity\GeoObject;
use Location\Coordinate;
use Location\Polygon as GeoPolygon;
use Location\Distance\Vincenty;
use Location\Distance\Haversine;
use Location\Formatter\Coordinate\DMS;
use Location\Formatter\Coordinate\DecimalDegrees;

/**
 * Service for advanced geographic calculations using phpgeo
 */
class GeoService
{
    /**
     * Convert a GeoObject to a phpgeo Coordinate
     */
    public function toCoordinate(GeoObject $object): Coordinate
    {
        if (!in_array($object->getGeometryType(), [GeoObject::GEOM_TYPE_POINT, GeoObject::GEOM_TYPE_CIRCLE])) {
            throw new \InvalidArgumentException('Object must be a point or circle');
        }
        
        return new Coordinate($object->getLatitude(), $object->getLongitude());
    }
    
    /**
     * Convert a GeoObject polygon to a phpgeo Polygon
     */
    public function toPolygon(GeoObject $object): GeoPolygon
    {
        if ($object->getGeometryType() !== GeoObject::GEOM_TYPE_POLYGON) {
            throw new \InvalidArgumentException('Object must be a polygon');
        }
        
        $polygon = new GeoPolygon();
        $coordinates = $object->getPolygonCoordinates();
        
        foreach ($coordinates as $point) {
            // GeoJSON format is [longitude, latitude]
            $polygon->addPoint(new Coordinate($point[1], $point[0]));
        }
        
        return $polygon;
    }
    
    /**
     * Calculate the area of a polygon in square meters
     */
    public function calculateArea(GeoObject $polygon): float
    {
        if ($polygon->getGeometryType() !== GeoObject::GEOM_TYPE_POLYGON) {
            throw new \InvalidArgumentException('Object must be a polygon');
        }
        
        $phpgeoPolygon = $this->toPolygon($polygon);
        
        return $phpgeoPolygon->getArea();
    }
    
    /**
     * Calculate the perimeter of a polygon in meters
     */
    public function calculatePerimeter(GeoObject $polygon): float
    {
        if ($polygon->getGeometryType() !== GeoObject::GEOM_TYPE_POLYGON) {
            throw new \InvalidArgumentException('Object must be a polygon');
        }
        
        $phpgeoPolygon = $this->toPolygon($polygon);
        
        return $phpgeoPolygon->getPerimeter(new Vincenty());
    }
    
    /**
     * Format a point's coordinates as Degrees/Minutes/Seconds
     */
    public function formatAsDMS(GeoObject $point, bool $useCardinalLetters = true): string
    {
        if (!in_array($point->getGeometryType(), [GeoObject::GEOM_TYPE_POINT, GeoObject::GEOM_TYPE_CIRCLE])) {
            throw new \InvalidArgumentException('Object must be a point or circle');
        }
        
        $coordinate = $this->toCoordinate($point);
        $formatter = new DMS();
        
        if ($useCardinalLetters) {
            $formatter->useCardinalLetters(true);
        }
        
        return $coordinate->format($formatter);
    }
    
    /**
     * Calculate the destination point given a starting point, bearing and distance
     * 
     * @param GeoObject $startPoint Starting point (must be Point or Circle)
     * @param float $bearing Bearing angle in degrees (0-360)
     * @param float $distance Distance in meters
     * @return array Coordinates as [latitude, longitude]
     */
    public function calculateDestinationPoint(GeoObject $startPoint, float $bearing, float $distance): array
    {
        if (!in_array($startPoint->getGeometryType(), [GeoObject::GEOM_TYPE_POINT, GeoObject::GEOM_TYPE_CIRCLE])) {
            throw new \InvalidArgumentException('Starting point must be a point or circle');
        }
        
        $coordinate = $this->toCoordinate($startPoint);
        
        $calculator = new \Location\Bearing\BearingEllipsoidal();
        $destination = $calculator->calculateDestination($coordinate, $bearing, $distance);
        
        return [$destination->getLat(), $destination->getLng()];
    }
} 