<?php

namespace App\Service;

use App\Entity\GeoObject;
use App\Entity\Map;
use App\Entity\Side;
use App\Repository\GeoObjectRepository;
use App\Repository\MapRepository;
use App\Repository\SideRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class GeoObjectService
{
    private EntityManagerInterface $entityManager;
    private GeoObjectRepository $geoObjectRepository;
    private MapRepository $mapRepository;
    private SideRepository $sideRepository;
    
    public function __construct(
        EntityManagerInterface $entityManager,
        GeoObjectRepository $geoObjectRepository,
        MapRepository $mapRepository,
        SideRepository $sideRepository
    ) {
        $this->entityManager = $entityManager;
        $this->geoObjectRepository = $geoObjectRepository;
        $this->mapRepository = $mapRepository;
        $this->sideRepository = $sideRepository;
    }
    
    /**
     * Create a new GeoObject
     */
    public function createGeoObject(array $data): array
    {
        error_log('=== GeoObjectService::createGeoObject ===');
        error_log('Received data: ' . json_encode($data));
        error_log('Data keys: ' . implode(', ', array_keys($data)));
        
        try {
            // Check required fields
            if (empty($data['title'])) {
                return [
                    'success' => false,
                    'message' => 'Title is required',
                    'status' => 400
                ];
            }
            
            if (empty($data['type'])) {
                return [
                    'success' => false,
                    'message' => 'Type is required',
                    'status' => 400
                ];
            }
            
            if (empty($data['geoJson'])) {
                return [
                    'success' => false,
                    'message' => 'GeoJSON is required',
                    'status' => 400
                ];
            }
            
            // Check mapId
            $mapId = $data['mapId'] ?? null;
            if (empty($mapId)) {
                return [
                    'success' => false,
                    'message' => 'Map ID is required',
                    'status' => 400
                ];
            }
            
            // Check if the map exists
            $map = $this->entityManager->getRepository(Map::class)->find($mapId);
            if (!$map) {
                return [
                    'success' => false,
                    'message' => 'Map not found',
                    'status' => 404
                ];
            }
            
            // Create a new GeoObject
            $geoObject = new GeoObject();
            $geoObject->setName($data['title']);
            $geoObject->setDescription($data['description'] ?? '');
            $geoObject->setGeometryType($data['type']);
            $geoObject->setTtl($data['ttl'] ?? 0);
            $geoObject->setMap($map); // Set the map
            
            // Set side if provided
            if (isset($data['sideId']) && !empty($data['sideId'])) {
                $side = $this->sideRepository->find($data['sideId']);
                if ($side) {
                    $geoObject->setSide($side);
                }
            }
            
            // Set icon URL if provided
            if (isset($data['iconUrl']) && !empty($data['iconUrl'])) {
                $geoObject->setIconUrl($data['iconUrl']);
            }
            
            // Process GeoJSON
            $geoJsonData = is_string($data['geoJson']) ? json_decode($data['geoJson'], true) : $data['geoJson'];
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'message' => 'Invalid GeoJSON format',
                    'status' => 400
                ];
            }
            $geoObject->setGeometry($geoJsonData);
            
            // Generate hash, if it is not provided
            if (empty($data['hash'])) {
                $geoObject->setHash(bin2hex(random_bytes(16)));
            } else {
                $geoObject->setHash($data['hash']);
            }
            
            // Save to database
            $this->entityManager->persist($geoObject);
            $this->entityManager->flush();
            
            return [
                'success' => true,
                'message' => 'GeoObject created successfully',
                'object' => $this->serializeGeoObject($geoObject),
                'status' => 201
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error creating GeoObject: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }
    
    /**
     * Update existing GeoObject
     */
    public function updateGeoObject(GeoObject $geoObject, array $data): array
    {
        if (empty($data)) {
            return [
                'success' => false,
                'message' => 'No data provided',
                'status' => Response::HTTP_BAD_REQUEST
            ];
        }
        
        try {
            // Update object data
            if (isset($data['title'])) {
                $geoObject->setName($data['title']);
            }
            
            if (isset($data['description'])) {
                $geoObject->setDescription($data['description']);
            }
            
            if (isset($data['type'])) {
                $geoObject->setGeometryType($data['type']);
            }
            
            // Update side if provided
            if (isset($data['sideId'])) {
                if (!empty($data['sideId'])) {
                    $side = $this->sideRepository->find($data['sideId']);
                    if ($side) {
                        $geoObject->setSide($side);
                    }
                } else {
                    // Clear side if empty value is provided
                    $geoObject->setSide(null);
                }
            }
            
            // Update icon URL if provided
            if (isset($data['iconUrl'])) {
                $geoObject->setIconUrl($data['iconUrl']);
            }
            
            // Update hash, if it is provided
            if (isset($data['hash'])) {
                $geoObject->setHash($data['hash']);
            }
            
            // Process GeoJSON, if it is provided
            if (isset($data['geoJson'])) {
                $geoJson = is_string($data['geoJson']) ? json_decode($data['geoJson'], true) : $data['geoJson'];
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return [
                        'success' => false,
                        'message' => 'Invalid GeoJSON format',
                        'status' => Response::HTTP_BAD_REQUEST
                    ];
                }
                
                $geoObject->setGeometry($geoJson);
            }
            
            // Save changes
            $this->entityManager->flush();
            
            return [
                'success' => true,
                'message' => 'Geo object updated successfully',
                'object' => $this->serializeGeoObject($geoObject),
                'status' => Response::HTTP_OK
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error updating geo object: ' . $e->getMessage(),
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR
            ];
        }
    }
    
    /**
     * Delete GeoObject
     */
    public function deleteGeoObject(GeoObject $geoObject): array
    {
        try {
            // Save Map ID before deleting object
            $mapId = $geoObject->getMap()->getId();
            
            // Delete object
            $this->entityManager->remove($geoObject);
            $this->entityManager->flush();
            
            return [
                'success' => true,
                'message' => 'Geo object deleted successfully',
                'mapId' => $mapId,
                'status' => Response::HTTP_OK
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error deleting geo object: ' . $e->getMessage(),
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR
            ];
        }
    }
    
    /**
     * Get all GeoObjects for a map
     */
    public function getGeoObjectsByMap(Map $map): array
    {
        try {
            $geoObjects = $this->geoObjectRepository->findBy(['map' => $map]);
            
            $result = [];
            foreach ($geoObjects as $object) {
                $result[] = $this->serializeGeoObject($object);
            }
            
            return [
                'success' => true,
                'objects' => $result,
                'status' => Response::HTTP_OK
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error fetching geo objects: ' . $e->getMessage(),
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR
            ];
        }
    }
    
    /**
     * Get one GeoObject by ID
     */
    public function getGeoObject(GeoObject $geoObject): array
    {
        try {
            return [
                'success' => true,
                'object' => $this->serializeGeoObject($geoObject, true),
                'status' => Response::HTTP_OK
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error fetching geo object: ' . $e->getMessage(),
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR
            ];
        }
    }
    
    /**
     * Get GeoObject by hash
     */
    public function getGeoObjectByHash(string $hash): array
    {
        try {
            $geoObject = $this->geoObjectRepository->findOneBy(['hash' => $hash]);
            
            if (!$geoObject) {
                return [
                    'success' => false,
                    'message' => 'Geo object not found for hash: ' . $hash,
                    'status' => Response::HTTP_NOT_FOUND
                ];
            }
            
            return [
                'success' => true,
                'object' => $this->serializeGeoObject($geoObject, true),
                'status' => Response::HTTP_OK
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error fetching geo object by hash: ' . $e->getMessage(),
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR
            ];
        }
    }
    
    /**
     * Search GeoObjects by criteria
     */
    public function searchGeoObjects(array $criteria): array
    {
        try {
            $searchCriteria = [];
            
            if (isset($criteria['hash'])) {
                $searchCriteria['hash'] = $criteria['hash'];
            }
            
            if (isset($criteria['mapId'])) {
                $map = $this->mapRepository->find($criteria['mapId']);
                if ($map) {
                    $searchCriteria['map'] = $map;
                }
            }
            
            if (empty($searchCriteria)) {
                return [
                    'success' => false,
                    'message' => 'Search criteria required (hash and/or mapId)',
                    'status' => Response::HTTP_BAD_REQUEST
                ];
            }
            
            $geoObjects = $this->geoObjectRepository->findBy($searchCriteria);
            
            $result = [];
            foreach ($geoObjects as $object) {
                $result[] = $this->serializeGeoObject($object, true);
            }
            
            return [
                'success' => true,
                'count' => count($result),
                'objects' => $result,
                'status' => Response::HTTP_OK
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error searching geo objects: ' . $e->getMessage(),
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR
            ];
        }
    }
    
    /**
     * Serialize GeoObject to array
     */
    private function serializeGeoObject(GeoObject $geoObject, bool $includeMapId = false): array
    {
        $data = [
            'id' => $geoObject->getId(),
            'hash' => $geoObject->getHash(),
            'title' => $geoObject->getName(),
            'description' => $geoObject->getDescription(),
            'type' => $geoObject->getGeometryType(),
            'geoJson' => $geoObject->getGeometry(),
            'ttl' => $geoObject->getTtl(),
            'iconUrl' => $geoObject->getIconUrl(),
            'side' => null,
            'sideId' => null,
            'isExpired' => $geoObject->isExpired(),
            'remainingTtl' => $geoObject->getRemainingTtl(),
            'createdAt' => $geoObject->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $geoObject->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
        
        // Add side information if available
        if ($geoObject->getSide()) {
            $side = $geoObject->getSide();
            $data['side'] = [
                'id' => $side->getId(),
                'name' => $side->getName(),
                'color' => $side->getColor()
            ];
            $data['sideId'] = $side->getId();
        }
        
        if ($includeMapId) {
            $data['mapId'] = $geoObject->getMap()->getId();
        }
        
        return $data;
    }
} 