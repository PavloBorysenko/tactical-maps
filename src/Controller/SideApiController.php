<?php

namespace App\Controller;

use App\Repository\SideRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public API endpoints for sides (without admin prefix)
 */
#[Route('/api/sides')]
class SideApiController extends AbstractController
{
    /**
     * API endpoint to get all sides for forms
     */
    #[Route('/list', name: 'side_public_api_list', methods: ['GET'])]
    public function list(SideRepository $sideRepository): JsonResponse
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