<?php

namespace App\Service;

use App\Entity\GeoObject;
use App\Entity\Map;
use App\Repository\GeoObjectRepository;
use App\Repository\MapRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class GeoObjectService
{
    private EntityManagerInterface $entityManager;
    private GeoObjectRepository $geoObjectRepository;
    private MapRepository $mapRepository;
    
    public function __construct(
        EntityManagerInterface $entityManager,
        GeoObjectRepository $geoObjectRepository,
        MapRepository $mapRepository
    ) {
        $this->entityManager = $entityManager;
        $this->geoObjectRepository = $geoObjectRepository;
        $this->mapRepository = $mapRepository;
    }
    
    /**
     * Create a new GeoObject
     */
    public function createGeoObject(array $data): array
    {
        // Check required data
        if (empty($data)) {
            return [
                'success' => false,
                'message' => 'No data provided',
                'status' => Response::HTTP_BAD_REQUEST
            ];
        }
        
        // Get Map from request
        $mapId = $data['mapId'] ?? null;
        if (!$mapId) {
            return [
                'success' => false,
                'message' => 'Map ID is required',
                'status' => Response::HTTP_BAD_REQUEST
            ];
        }
        
        $map = $this->mapRepository->find($mapId);
        if (!$map) {
            return [
                'success' => false,
                'message' => 'Map not found',
                'status' => Response::HTTP_NOT_FOUND
            ];
        }
        
        try {
            $geoObject = new GeoObject();
            
            // Set data from request
            $geoObject->setMap($map);
            $geoObject->setTitle($data['title'] ?? 'Unnamed');
            $geoObject->setDescription($data['description'] ?? '');
            $geoObject->setType($data['type'] ?? 'point');
            
            // Set hash, if it is provided
            if (isset($data['hash'])) {
                $geoObject->setHash($data['hash']);
            } else {
                // Generate unique hash, if it is not provided
                $geoObject->setHash(bin2hex(random_bytes(16)));
            }
            
            // Process GeoJSON
            if (isset($data['geoJson'])) {
                $geoJson = is_string($data['geoJson']) ? json_decode($data['geoJson'], true) : $data['geoJson'];
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return [
                        'success' => false,
                        'message' => 'Invalid GeoJSON format',
                        'status' => Response::HTTP_BAD_REQUEST
                    ];
                }
                
                $geoObject->setGeoJson($geoJson);
            } else {
                return [
                    'success' => false,
                    'message' => 'GeoJSON is required',
                    'status' => Response::HTTP_BAD_REQUEST
                ];
            }
            
            // Save object
            $this->entityManager->persist($geoObject);
            $this->entityManager->flush();
            
            return [
                'success' => true,
                'message' => 'Geo object created successfully',
                'id' => $geoObject->getId(),
                'hash' => $geoObject->getHash(),
                'object' => $this->serializeGeoObject($geoObject),
                'status' => Response::HTTP_OK
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error creating geo object: ' . $e->getMessage(),
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR
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
                $geoObject->setTitle($data['title']);
            }
            
            if (isset($data['description'])) {
                $geoObject->setDescription($data['description']);
            }
            
            if (isset($data['type'])) {
                $geoObject->setType($data['type']);
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
                
                $geoObject->setGeoJson($geoJson);
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
            'title' => $geoObject->getTitle(),
            'description' => $geoObject->getDescription(),
            'type' => $geoObject->getType(),
            'geoJson' => $geoObject->getGeoJson(),
        ];
        
        if ($includeMapId) {
            $data['mapId'] = $geoObject->getMap()->getId();
        }
        
        return $data;
    }
} 