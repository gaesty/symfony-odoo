<?php

namespace App\Controller\OdooJIT;

use App\Service\OdooService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends AbstractController
{
    #[Route('/api/search/client', name: 'api_search_client')]
    public function searchClient(Request $request, OdooService $service): JsonResponse
    {
        $term = $request->query->get('term', '');
        if (strlen($term) < 2) {
            return $this->json([]);
        }
        
        try {
            // Recherche Odoo avec limitation
            $results = $service->getOdooModelData(
                'res.partner',
                [['name', 'ilike', $term]],
                ['id', 'name'],
                ['limit' => 20]
            );
            
            return $this->json($results);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    #[Route('/api/search/product', name: 'api_search_product')]
    public function searchProduct(Request $request, OdooService $service): JsonResponse
    {
        $term = $request->query->get('term', '');
        if (strlen($term) < 2) {
            return $this->json([]);
        }
        
        try {
            // Recherche Odoo avec limitation
            $results = $service->getOdooModelData(
                'product.template',
                [['display_name', 'ilike', $term]],
                ['id', 'display_name', 'default_code'],
                ['limit' => 20]
            );
            
            return $this->json($results);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    #[Route('/api/search/bin', name: 'api_search_bin')]
    public function searchBin(Request $request, OdooService $service): JsonResponse
    {
        $term = $request->query->get('term', '');
        if (strlen($term) < 2) {
            return $this->json([]);
        }
        
        try {
            // Recherche Odoo avec limitation
            $results = $service->getOdooModelData(
                'aa.just.in.time',
                [['name', 'ilike', $term]],
                ['id', 'name', 'partner_id'],
                ['limit' => 20]
            );
            
            return $this->json($results);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}