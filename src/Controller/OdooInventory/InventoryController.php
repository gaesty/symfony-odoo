<?php

namespace App\Controller\OdooInventory;

use App\Service\OdooService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InventoryController extends AbstractController
{
    #[Route('/odoo_inventory', name: 'odoo_inventory')]
    public function viewAllInventory(Request $request, OdooService $service): Response
    {
        // Paramètres de pagination
        $page = $request->query->getInt('page', 1);
        $limit = 20; // Nombre d'éléments par page
        $offset = ($page - 1) * $limit;
        $searchTerm = $request->query->get('search', '');

        // Limiter le nombre de pages maximal
        $maxPages = 1000;

        try {
            // Construire les filtres de recherche
            $domain = [];
            if (!empty($searchTerm)) {
                $domain = [
                    '|',
                    '|',
                    '|',
                    ['name', 'ilike', $searchTerm],
                    ['default_code', 'ilike', $searchTerm],
                    ['display_name', 'ilike', $searchTerm],
                    ['barcode', 'ilike', $searchTerm]
                ];
            }

            // 1. Obtenir le nombre total pour la pagination
            $totalCount = $service->countOdooModelData('product.template', $domain);
            $totalPages = ceil($totalCount / $limit);

            // Limiter le nombre de pages affiché
            if ($totalPages > $maxPages) {
                $totalPages = $maxPages;
            }

            // 2. Récupérer les données paginées
            $fields = ['id', 'name', 'display_name', 'default_code', 'categ_id', 'list_price', 'qty_available', 'active'];

            // Simplifier les options
            $options = [
                'limit' => $limit,
                'offset' => $offset,
                'order' => 'id ASC'
            ];

            $result = $service->getOdooModelData('product.template', $domain, $fields, $options);
            // dump($result); // Ne pas ajouter die; pour voir le rendu de la page

        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue: ' . $e->getMessage());

            return $this->render('odoo/inventory.html.twig', [
                'result' => [],
                'currentPage' => $page,
                'totalPages' => 1,
                'totalCount' => 0,
                'searchTerm' => $searchTerm
            ]);
        }

        return $this->render('odoo/inventory.html.twig', [
            'result' => $result,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'searchTerm' => $searchTerm
        ]);
    }

    #[Route('/odoo_inventory/create', name: 'odoo_inventory_create', methods: ['GET', 'POST'])]
    public function createInventoryRecord(Request $request, OdooService $service): Response
    {
        // Récupération des catégories de produits pour le formulaire
        $productCategory = $service->getOdooModelData(
            'product.category',
            [],
            ['id', 'complete_name'],
            ['limit' => 100]
        );

        // Récupération des unités de mesure
        $uomList = $service->getOdooModelData(
            'uom.uom',
            [],
            ['id', 'name'],
            ['limit' => 50]
        );

        // Récupération des devises
        $currencyList = $service->getOdooModelData(
            'res.currency',
            [],
            ['id', 'name'],
            ['limit' => 20]
        );

        if ($request->isMethod('POST')) {
            try {
                $data = $request->request->all();

                // Validation du nom obligatoire
                if (empty($data['display_name'])) {
                    $this->addFlash('error', 'Le nom du produit est obligatoire');
                    return $this->render('odoo/inventory_create.html.twig', [
                        'productCategory' => $productCategory,
                        'uomList' => $uomList,
                        'currencyList' => $currencyList
                    ]);
                }

                // Préparation des données pour la création
                $createData = [
                    'name' => $data['display_name'],
                    'display_name' => $data['display_name'],
                    'default_code' => $data['default_code'] ?? '',
                    'description' => $data['description'] ?? '',
                    'description_sale' => $data['description_sale'] ?? '',
                    'list_price' => (float) ($data['list_price'] ?? 0),
                    'standard_price' => (float) ($data['standard_price'] ?? 0),
                    'qty_available' => (float) ($data['qty_available'] ?? 0),
                    'weight' => (float) ($data['weight'] ?? 0),
                    'volume' => (float) ($data['volume'] ?? 0),
                    'categ_id' => (int) $data['categ_id'],
                    'type' => $data['type'] ?? 'product',
                    'active' => isset($data['active']) ? true : false,
                    'sale_ok' => isset($data['sale_ok']) ? true : false,
                    'purchase_ok' => isset($data['purchase_ok']) ? true : false
                ];

                // Ajouter les champs optionnels s'ils sont définis
                if (!empty($data['uom_id'])) {
                    $createData['uom_id'] = (int) $data['uom_id'];
                }

                if (!empty($data['uom_po_id'])) {
                    $createData['uom_po_id'] = (int) $data['uom_po_id'];
                }

                if (!empty($data['currency_id'])) {
                    $createData['currency_id'] = (int) $data['currency_id'];
                }

                // Création de l'enregistrement
                $newId = $service->createOdoo('product.template', $createData);

                $this->addFlash('success', 'Produit créé avec succès (ID: ' . $newId . ')');
                return $this->redirectToRoute('odoo_inventory');

            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création: ' . $e->getMessage());
            }
        }

        return $this->render('odoo/inventory_create.html.twig', [
            'productCategory' => $productCategory,
            'uomList' => $uomList,
            'currencyList' => $currencyList
        ]);
    }

    #[Route('/odoo_inventory/info/{id}', name: 'odoo_inventory_info', requirements: ['id' => '\d+'])]
    public function viewInventoryInfo(OdooService $service, int $id): Response
    {
        try {
            // 1. Récupérer les informations de base du produit
            $result = $service->getOdooModelData(
                'product.template',
                [['id', '=', $id]],
                [
                    // Informations générales
                    'id',
                    'name',
                    'display_name',
                    'default_code',
                    'barcode',
                    'active',
                    'categ_id',
                    'type',
                    'create_date',
                    'create_uid',
                    'version',
                    'version_name',

                    // Prix et conditions
                    'list_price',
                    'standard_price',
                    'currency_id',
                    'cost_currency_id',
                    'taxes_id',
                    'supplier_taxes_id',

                    // Ventes et achats
                    'sale_ok',
                    'purchase_ok',
                    'sale_delay',
                    'purchase_method',
                    'invoice_policy',
                    'expense_policy',

                    // Inventaire
                    'qty_available',
                    'virtual_available',
                    'incoming_qty',
                    'outgoing_qty',
                    'tracking',
                    'weight',
                    'volume',
                    'responsible_id',
                    'uom_id',
                    'uom_po_id',
                    'product_variant_ids',
                    'product_variant_count',

                    // Descriptions
                    'description',
                    'description_sale',
                    'description_purchase',
                    'description_picking',
                    'description_pickingout',
                    'description_pickingin',

                    // Comptabilité
                    'property_account_income_id',
                    'property_account_expense_id',
                    'property_account_creditor_price_difference',

                    // Informations douanières
                    'hs_code',
                    'country_of_origin',
                    'taric_code',
                    'intrastat_code_id',

                    // Inspections et qualité
                    'final_inspection',
                    'ref_report_code_id',
                    'shelf_life',
                    'shelf_life_type',
                    'dim_inspection_ids',
                    'thred_inspection_ids',
                    'visual_inspection_ids',
                    'doc_inspection_ids'
                ]
            );

            if (empty($result)) {
                $this->addFlash('error', 'Produit non trouvé (ID: ' . $id . ')');
                return $this->redirectToRoute('odoo_inventory');
            }

            $productData = $result[0];

            // 2. Récupérer les données associées
            $additionalData = [];

            // 2.1 Récupérer les fournisseurs
            if (method_exists($service, 'getOdooModelData')) {
                try {
                    $additionalData['suppliers'] = $service->getOdooModelData(
                        'product.supplierinfo',
                        [['product_tmpl_id', '=', $id]],
                        ['id', 'name', 'product_name', 'product_code', 'price', 'min_qty', 'delay', 'currency_id'],
                        ['limit' => 10, 'order' => 'price ASC']
                    );
                } catch (\Exception $e) {
                    $additionalData['suppliers'] = [];
                }

                // 2.2 Récupérer les clients
                try {
                    $additionalData['customers'] = $service->getOdooModelData(
                        'product.customerinfo',
                        [['product_tmpl_id', '=', $id]],
                        ['id', 'name', 'product_name', 'product_code', 'price', 'min_qty', 'delay', 'currency_id'],
                        ['limit' => 10, 'order' => 'price ASC']
                    );
                } catch (\Exception $e) {
                    $additionalData['customers'] = [];
                }

                // 2.3 Récupérer les conditionnements
                try {
                    $additionalData['packaging'] = $service->getOdooModelData(
                        'product.packaging',
                        [['product_id', 'in', $productData['product_variant_ids']]],
                        ['id', 'name', 'qty', 'barcode', 'product_id'],
                        ['limit' => 10]
                    );
                } catch (\Exception $e) {
                    $additionalData['packaging'] = [];
                }

                // 2.4 Récupérer les ECOs (ordres d'ingénierie)
                try {
                    $additionalData['ecos'] = $service->getOdooModelData(
                        'mrp.eco',
                        [['product_tmpl_id', '=', $id]],
                        ['id', 'name', 'stage_id', 'state', 'create_date'],
                        ['limit' => 10, 'order' => 'create_date DESC']
                    );
                } catch (\Exception $e) {
                    $additionalData['ecos'] = [];
                }

                // 2.5 Récupérer les inspections détaillées si les IDs sont disponibles
                // Inspections visuelles
                if (!empty($productData['visual_inspection_ids'])) {
                    try {
                        $additionalData['visual_inspections'] = $service->getOdooModelData(
                            'visual.inspection',
                            [['id', 'in', $productData['visual_inspection_ids']]],
                            ['id', 'name', 'value', 'description']
                        );
                    } catch (\Exception $e) {
                        $additionalData['visual_inspections'] = [];
                    }
                }

                // Inspections documentaires
                if (!empty($productData['doc_inspection_ids'])) {
                    try {
                        $additionalData['doc_inspections'] = $service->getOdooModelData(
                            'document.inspection',
                            [['id', 'in', $productData['doc_inspection_ids']]],
                            ['id', 'name', 'value']
                        );
                    } catch (\Exception $e) {
                        $additionalData['doc_inspections'] = [];
                    }
                }

                // Inspections dimensionnelles
                if (!empty($productData['dim_inspection_ids'])) {
                    try {
                        $additionalData['dim_inspections'] = $service->getOdooModelData(
                            'dimension.inspection',
                            [['id', 'in', $productData['dim_inspection_ids']]],
                            ['id', 'name', 'nominal', 'upper_limit', 'lower_limit']
                        );
                    } catch (\Exception $e) {
                        $additionalData['dim_inspections'] = [];
                    }
                }

                // Inspections de filetage
                if (!empty($productData['thred_inspection_ids'])) {
                    try {
                        $additionalData['thread_inspections'] = $service->getOdooModelData(
                            'thred.inspection',
                            [['id', 'in', $productData['thred_inspection_ids']]],
                            ['id', 'name', 'value', 'description']
                        );
                    } catch (\Exception $e) {
                        $additionalData['thread_inspections'] = [];
                    }
                }
            }

            return $this->render('odoo/inventory_info.html.twig', [
                'result' => $productData,
                'additional' => $additionalData,
                // Extraire les données spécifiques pour un accès direct dans le template
                'suppliers' => $additionalData['suppliers'] ?? [],
                'customers' => $additionalData['customers'] ?? [],
                'packaging' => $additionalData['packaging'] ?? [],
                'ecos' => $additionalData['ecos'] ?? [],
                'visual_inspections' => $additionalData['visual_inspections'] ?? [],
                'doc_inspections' => $additionalData['doc_inspections'] ?? [],
                'dim_inspections' => $additionalData['dim_inspection_ids'] ?? [],
                'thread_inspections' => $additionalData['thread_inspections'] ?? []
            ]);

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la récupération du produit: ' . $e->getMessage());
            return $this->redirectToRoute('odoo_inventory');
        }
    }

    #[Route('/api/odoo_inventory', name: 'api_odoo_inventory', methods: ['GET'])]
    public function getInventoryData(Request $request, OdooService $service): Response
    {
        // Paramètres de pagination
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);
        $offset = ($page - 1) * $limit;
        $searchTerm = $request->query->get('search', '');

        try {
            // Construire les filtres de recherche
            $domain = [];
            if (!empty($searchTerm)) {
                $domain = [
                    '|',
                    '|',
                    '|',
                    ['name', 'ilike', $searchTerm],
                    ['default_code', 'ilike', $searchTerm],
                    ['display_name', 'ilike', $searchTerm],
                    ['barcode', 'ilike', $searchTerm]
                ];
            }

            // Obtenir le nombre total pour la pagination
            $totalCount = $service->countOdooModelData('product.template', $domain);

            // Récupérer les données paginées
            $fields = ['id', 'name', 'display_name', 'default_code', 'categ_id', 'list_price', 'qty_available', 'active'];
            $options = [
                'limit' => $limit,
                'offset' => $offset,
                'order' => 'id ASC'
            ];

            $result = $service->getOdooModelData('product.template', $domain, $fields, $options);

            return $this->json([
                'success' => true,
                'data' => $result,
                'totalCount' => $totalCount,
                'totalPages' => ceil($totalCount / $limit)
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/odoo_inventory/debug', name: 'odoo_inventory_debug')]
    public function debugInventory(OdooService $service): Response
    {
        try {
            // Récupérer seulement les IDs et noms pour les 20 premiers produits
            $result = $service->getOdooModelData(
                'product.template',
                [],
                ['id', 'name'],
                ['limit' => 20, 'order' => 'id ASC']
            );

            return $this->render('odoo/inventory_debug.html.twig', [
                'products' => $result
            ]);
        } catch (\Exception $e) {
            return new Response('Erreur : ' . $e->getMessage());
        }
    }
}