<?php

namespace App\Controller;

use App\Entity\GeoObject;
use App\Entity\Map;
use App\Service\GeoObjectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/geo-object')]
class GeoObjectController extends AbstractController
{
    private GeoObjectService $geoObjectService;
    
    public function __construct(GeoObjectService $geoObjectService)
    {
        $this->geoObjectService = $geoObjectService;
    }

    /**
     * API for creating new GeoObject
     */
    #[Route('/new', name: 'geo_object_new', methods: ['POST'])]
    public function new(Request $request, GeoObjectService $geoObjectService): JsonResponse
    {
        try {
            // Get data from JSON body
            $content = $request->getContent();
            $data = json_decode($content, true);
            
            // If JSON was not passed, try to get data from form
            if (empty($data)) {
                if ($request->request->has('geo_object')) {
                    $geoObjectData = $request->request->get('geo_object');
                    $data = [
                        'title' => $geoObjectData['title'] ?? '',
                        'description' => $geoObjectData['description'] ?? '',
                        'type' => $geoObjectData['type'] ?? '',
                        'ttl' => (int)($geoObjectData['ttl'] ?? 0),
                        'geoJson' => $geoObjectData['geoJson'] ?? '',
                        'hash' => $geoObjectData['hash'] ?? '',
                        'mapId' => $geoObjectData['mapId'] ?? null
                    ];
                } else {
                    // Alternative way to get data
                    $data = [
                        'title' => $request->request->get('title', ''),
                        'description' => $request->request->get('description', ''),
                        'type' => $request->request->get('type', ''),
                        'ttl' => (int)$request->request->get('ttl', 0),
                        'geoJson' => $request->request->get('geoJson', ''),
                        'hash' => $request->request->get('hash', ''),
                        'mapId' => $request->request->get('mapId')
                    ];
                }
            }
            
            // Log the received data for debugging
            error_log('Raw request content: ' . $content);
            error_log('Parsed data for new GeoObject: ' . json_encode($data));
            
            // Check if data was received
            if (empty($data)) {
                return $this->json([
                    'success' => false,
                    'message' => 'No data received'
                ], 400);
            }
            
            // Create object through service
            $result = $geoObjectService->createGeoObject($data);
            
            return $this->json($result, $result['status'] ?? 200);
            
        } catch (\Exception $e) {
            error_log('Exception in GeoObjectController::new: ' . $e->getMessage());
            return $this->json([
                'success' => false,
                'message' => 'Error processing request: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * API for updating existing GeoObject
     */
    #[Route('/{id}/update', name: 'geo_object_update', methods: ['POST'])]
    public function update(Request $request, GeoObject $geoObject): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // If data is not in JSON, check the form
        if (!$data) {
            $data = $request->request->all();
        }
        
        $result = $this->geoObjectService->updateGeoObject($geoObject, $data);
        $statusCode = $result['status'] ?? Response::HTTP_OK;
        unset($result['status']); // Remove status code from response
        
        return $this->json($result, $statusCode);
    }
    
    /**
     * API for deleting GeoObject
     */
    #[Route('/{id}/delete', name: 'geo_object_delete', methods: ['POST'])]
    public function delete(GeoObject $geoObject): JsonResponse
    {
        $result = $this->geoObjectService->deleteGeoObject($geoObject);
        $statusCode = $result['status'] ?? Response::HTTP_OK;
        unset($result['status']); // Remove status code from response
        
        return $this->json($result, $statusCode);
    }
    
    /**
     * API for getting all GeoObject for map
     */
    #[Route('/by-map/{map}', name: 'geo_object_by_map', methods: ['GET'])]
    public function getByMap(Map $map): JsonResponse
    {
        $result = $this->geoObjectService->getGeoObjectsByMap($map);
        $statusCode = $result['status'] ?? Response::HTTP_OK;
        unset($result['status']); // Remove status code from response
        
        return $this->json($result, $statusCode);
    }
    
    /**
     * API for getting one GeoObject by ID
     */
    #[Route('/{id}', name: 'geo_object_get', methods: ['GET'])]
    public function getOne(GeoObject $geoObject): JsonResponse
    {
        $result = $this->geoObjectService->getGeoObject($geoObject);
        $statusCode = $result['status'] ?? Response::HTTP_OK;
        unset($result['status']); // Remove status code from response
        
        return $this->json($result, $statusCode);
    }
    
    /**
     * API for searching GeoObject by hash
     */
    #[Route('/hash/{hash}', name: 'geo_object_by_hash', methods: ['GET'])]
    public function getByHash(string $hash): JsonResponse
    {
        $result = $this->geoObjectService->getGeoObjectByHash($hash);
        $statusCode = $result['status'] ?? Response::HTTP_OK;
        unset($result['status']); // Remove status code from response
        
        return $this->json($result, $statusCode);
    }
    
    /**
     * API for searching all GeoObject by hash and mapId
     */
    #[Route('/search', name: 'geo_object_search', methods: ['GET'])]
    public function searchObjects(Request $request): JsonResponse
    {
        $criteria = [
            'hash' => $request->query->get('hash'),
            'mapId' => $request->query->get('mapId')
        ];
        
        $result = $this->geoObjectService->searchGeoObjects($criteria);
        $statusCode = $result['status'] ?? Response::HTTP_OK;
        unset($result['status']); // Remove status code from response
        
        return $this->json($result, $statusCode);
    }
} 