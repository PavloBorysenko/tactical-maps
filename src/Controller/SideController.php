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
            $entityManager->remove($side);
            $entityManager->flush();
            
            $this->addFlash('success', 'Side deleted successfully');
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
} 