<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

error_log('=== IconController.php loaded ===');

class IconController extends AbstractController
{
    /**
     * Get list of available custom icons
     */
    #[Route('/api/icons', name: 'geo_object_icons', methods: ['GET'])]
    public function getCustomIcons(): JsonResponse
    {
        error_log('=== getCustomIcons called ===');
        
        $iconsDir = $this->getParameter('kernel.project_dir') . '/public/assets/icons/custom';
        $icons = [];
        
        error_log('Icons directory: ' . $iconsDir);
        
        if (is_dir($iconsDir)) {
            $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];
            $files = scandir($iconsDir);
            
            error_log('Files found: ' . json_encode($files));
            
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                
                $filePath = $iconsDir . '/' . $file;
                if (is_file($filePath)) {
                    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    
                    if (in_array($extension, $allowedExtensions)) {
                        $icons[] = [
                            'filename' => $file,
                            'name' => pathinfo($file, PATHINFO_FILENAME),
                            'url' => '/assets/icons/custom/' . $file,
                            'extension' => $extension
                        ];
                    }
                }
            }
        } else {
            error_log('Icons directory does not exist');
        }
        
        error_log('Icons found: ' . json_encode($icons));
        
        // Sort icons by name
        usort($icons, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        
        return new JsonResponse([
            'success' => true,
            'icons' => $icons
        ]);
    }
} 