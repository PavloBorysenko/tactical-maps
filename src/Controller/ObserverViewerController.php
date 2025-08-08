<?php

namespace App\Controller;

use App\Entity\Observer;
use App\Repository\ObserverRepository;
use App\Repository\GeoObjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ObserverViewerController extends AbstractController
{
    #[Route('/observer/{token}', name: 'observer_viewer', methods: ['GET'])]
    public function view(
        string $token,
        ObserverRepository $observerRepository,
        GeoObjectRepository $geoObjectRepository
    ): Response {
        // Find observer by access token
        $observer = $observerRepository->findByAccessToken($token);
        
        if (!$observer) {
            throw new NotFoundHttpException('Observer not found or invalid token');
        }
        
        // Get the map associated with this observer
        $map = $observer->getMap();
        
        // Get ALL active geo objects for this map (ignoring side visibility constraints)
        // Observer should see all objects, not filtered by side visibility
        $geoObjects = $geoObjectRepository->findActiveByMapForObserver($map);
        
        // Debug information
        error_log('=== Observer Debug ===');
        error_log('Observer: ' . $observer->getName());
        error_log('Map: ' . $map->getTitle() . ' (ID: ' . $map->getId() . ')');
        error_log('Active objects found: ' . count($geoObjects));
        foreach ($geoObjects as $obj) {
            error_log('- Object: ' . $obj->getName() . ' (TTL: ' . $obj->getTtl() . ', Type: ' . $obj->getGeometryType() . ')');
        }
        error_log('======================');
        
        return $this->render('observer_viewer/view.html.twig', [
            'observer' => $observer,
            'map' => $map,
            'geoObjects' => $geoObjects,
        ]);
    }
} 