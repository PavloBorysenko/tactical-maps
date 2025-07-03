<?php

namespace App\Controller;

use App\Entity\Map;
use App\Form\MapType;
use App\Repository\MapRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\GeoObjectService;
use Symfony\Component\Form\FormFactoryInterface;
use App\Form\GeoObjectType;

#[Route('/admin/maps')]
class MapController extends AbstractController
{
    #[Route('/', name: 'map_index', methods: ['GET'])]
    public function index(MapRepository $mapRepository): Response
    {
        return $this->render('map/index.html.twig', [
            'maps' => $mapRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'map_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $map = new Map();
        // Set default values (center and zoom level)
        $map->setCenterLat(51.505);
        $map->setCenterLng(-0.09);
        $map->setZoomLevel(13);
        
        $form = $this->createForm(MapType::class, $map);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($map);
            $entityManager->flush();

            return $this->redirectToRoute('map_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('map/new.html.twig', [
            'map' => $map,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'map_show', methods: ['GET'])]
    public function show(Map $map, GeoObjectService $geoObjectService, FormFactoryInterface $formFactory): Response
    {
        // Get all geo objects for this map
        $geoObjectsResult = $geoObjectService->getGeoObjectsByMap($map);
        $geoObjects = $geoObjectsResult['success'] ? $geoObjectsResult['objects'] : [];
        
        // Create an empty form for new geo objects
        $geoObjectForm = $formFactory->create(GeoObjectType::class, null, [
            'is_edit' => false,
        ]);
        
        return $this->render('map/show.html.twig', [
            'map' => $map,
            'geoObjects' => $geoObjects,
            'geoObjectForm' => $geoObjectForm->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'map_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Map $map, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MapType::class, $map);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('map_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('map/edit.html.twig', [
            'map' => $map,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'map_delete', methods: ['POST'])]
    public function delete(Request $request, Map $map, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$map->getId(), $request->request->get('_token'))) {
            $entityManager->remove($map);
            $entityManager->flush();
        }

        return $this->redirectToRoute('map_index', [], Response::HTTP_SEE_OTHER);
    }
} 