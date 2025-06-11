<?php

namespace App\Controller;

use App\Entity\Side;
use App\Form\SideType;
use App\Repository\SideRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/sides')]
class SideController extends AbstractController
{
    #[Route('/', name: 'side_index', methods: ['GET'])]
    public function index(SideRepository $sideRepository): Response
    {
        return $this->render('side/index.html.twig', [
            'sides' => $sideRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'side_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $side = new Side();
        $form = $this->createForm(SideType::class, $side);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($side);
            $entityManager->flush();

            $this->addFlash('success', 'Side created successfully');
            return $this->redirectToRoute('side_index');
        }

        return $this->render('side/new.html.twig', [
            'side' => $side,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'side_show', methods: ['GET'])]
    public function show(Side $side): Response
    {
        return $this->render('side/show.html.twig', [
            'side' => $side,
        ]);
    }

    #[Route('/{id}/edit', name: 'side_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Side $side, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SideType::class, $side);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Side updated successfully');
            return $this->redirectToRoute('side_index');
        }

        return $this->render('side/edit.html.twig', [
            'side' => $side,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'side_delete', methods: ['POST'])]
    public function delete(Request $request, Side $side, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$side->getId(), $request->request->get('_token'))) {
            try {
                // Check if there are geo objects associated with this side
                $geoObjectsCount = $entityManager->createQuery(
                    'SELECT COUNT(g.id) FROM App\Entity\GeoObject g WHERE g.side = :side'
                )->setParameter('side', $side)->getSingleScalarResult();

                if ($geoObjectsCount > 0) {
                    $this->addFlash('warning', 
                        sprintf('Cannot delete side "%s" because it has %d associated geo objects. Please remove or reassign these objects first.', 
                        $side->getName(), $geoObjectsCount)
                    );
                    return $this->redirectToRoute('side_index');
                }

                $entityManager->remove($side);
                $entityManager->flush();
                
                $this->addFlash('success', sprintf('Side "%s" deleted successfully', $side->getName()));
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting side: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token. Please try again.');
        }

        return $this->redirectToRoute('side_index');
    }

    /**
     * API endpoint to get all sides
     */
    #[Route('/api/list', name: 'side_api_list', methods: ['GET'])]
    public function apiList(SideRepository $sideRepository): JsonResponse
    {
        try {
            $sides = $sideRepository->findAll();
            
            $result = [];
            foreach ($sides as $side) {
                $result[] = [
                    'id' => $side->getId(),
                    'name' => $side->getName(),
                    'color' => $side->getColor(),
                    'description' => $side->getDescription()
                ];
            }
            
            return $this->json([
                'success' => true,
                'sides' => $result
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error fetching sides: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Force delete a side by setting all associated geo objects' side to null
     */
    #[Route('/{id}/force-delete', name: 'side_force_delete', methods: ['POST'])]
    public function forceDelete(Request $request, Side $side, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('force_delete'.$side->getId(), $request->request->get('_token'))) {
            try {
                // First, set all associated geo objects' side to null
                $entityManager->createQuery(
                    'UPDATE App\Entity\GeoObject g SET g.side = NULL WHERE g.side = :side'
                )->setParameter('side', $side)->execute();

                // Then delete the side
                $entityManager->remove($side);
                $entityManager->flush();
                
                $this->addFlash('success', sprintf('Side "%s" deleted successfully. Associated geo objects were unassigned.', $side->getName()));
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting side: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token. Please try again.');
        }

        return $this->redirectToRoute('side_index');
    }
} 