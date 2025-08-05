<?php
namespace App\Controller;
//  src\Controller\OdooController.php
use App\Service\OdooService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OdooController extends AbstractController
{
  #[Route('/odoo_partners_list', name: 'odoo_partners')]
  public function viewAllPartners(OdooService $service): Response
  {
    $result = $service->getOdooModelData('res.partner', [], ['id', 'name', 'email', 'phone']);

    return $this->render('odoo/partners.html.twig', [
      'result' => $result,
    ]);
  }

  #[Route('/odoo_partner/{id}', name: 'odoo_partner_view', requirements: ['id' => '\d+'])]
  public function viewPartnerById(OdooService $service, int $id): Response
  {
    $partner = $service->getOdooModelData('res.partner', [['id', '=', $id]], []);

    if (!$partner) {
      throw $this->createNotFoundException('Partner not found');
    }

    return $this->render('odoo/partner_view.html.twig', [
      'partner' => $partner[0], // Expecting only one result since we're filtering by ID
    ]);
  }

  // Create a new partner
  #[Route('/odoo_partner/create', name: 'odoo_partner_create', methods: ['GET', 'POST'])]
  public function createPartner(Request $request, OdooService $service): Response
  {
    // Récupération des pays pour la liste déroulante
    $countryList = $service->getOdooModelData(
        'res.country',
        [],
        ['id', 'name'],
        ['order' => 'name ASC']
    );

    if ($request->isMethod('POST')) {
      try {
        $data = $request->request->all();
        
        // Validation des champs requis
        if (empty($data['name'])) {
          $this->addFlash('error', 'Le nom du partenaire est obligatoire');
          return $this->render('odoo/create_partner.html.twig', [
            'countryList' => $countryList
          ]);
        }
        
        // Préparation des données pour la création
        $createData = [
            'name' => $data['name'],
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'mobile' => $data['mobile'] ?? '',
            'street' => $data['street'] ?? '',
            'city' => $data['city'] ?? '',
            'zip' => $data['zip'] ?? '',
            'website' => $data['website'] ?? '',
            'vat' => $data['vat'] ?? '',
            'comment' => $data['comment'] ?? '',
            'active' => true
        ];
        
        // Ajout du pays s'il est défini
        if (!empty($data['country_id'])) {
            $createData['country_id'] = (int)$data['country_id'];
        }
        
        // Ajout du type de partenaire
        $createData['company_type'] = $data['company_type'] ?? 'company';
        $createData['is_company'] = ($data['company_type'] ?? 'company') === 'company';
        
        // Création de l'enregistrement
        $newId = $service->createOdoo('res.partner', $createData);
        
        $this->addFlash('success', 'Partenaire créé avec succès (ID: ' . $newId . ')');
        return $this->redirectToRoute('odoo_partners');
        
      } catch (\Exception $e) {
        $this->addFlash('error', 'Erreur lors de la création du partenaire: ' . $e->getMessage());
      }
    }
    
    return $this->render('odoo/create_partner.html.twig', [
        'countryList' => $countryList
    ]);
  }
  
  // Autres méthodes existantes...
  
  // Méthodes pour la gestion de l'inventaire
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
                '|', '|', '|',
                ['name', 'ilike', '%' . $searchTerm . '%'],
                ['default_code', 'ilike', '%' . $searchTerm . '%'],
                ['display_name', 'ilike', '%' . $searchTerm . '%'],
                ['barcode', 'ilike', '%' . $searchTerm . '%']
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
        $fields = [
            'id', 'name', 'display_name', 'default_code', 
            'categ_id', 'type', 'qty_available', 'state',
            'list_price', 'currency_id', 'active'
        ];
        
        $options = [
            'limit' => $limit,
            'offset' => $offset,
            'order' => 'id ASC' // Tri cohérent
        ];
        
        $result = $service->getOdooModelData('product.template', $domain, $fields, $options);
        
        // Log pour débogage
        error_log(sprintf(
            'Page: %d, Offset: %d, Produits trouvés: %d sur %d total',
            $page,
            $offset,
            count($result),
            $totalCount
        ));
        
    } catch (\Exception $e) {
        error_log('Erreur inventaire: ' . $e->getMessage());
        $this->addFlash('error', 'Une erreur est survenue lors du chargement des produits: ' . $e->getMessage());
        
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
  public function createInventory(Request $request, OdooService $service): Response
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
          'list_price' => (float)($data['list_price'] ?? 0),
          'standard_price' => (float)($data['standard_price'] ?? 0),
          'qty_available' => (float)($data['qty_available'] ?? 0),
          'weight' => (float)($data['weight'] ?? 0),
          'volume' => (float)($data['volume'] ?? 0),
          'categ_id' => (int)$data['categ_id'],
          'type' => $data['type'] ?? 'product',
          'active' => isset($data['active']) ? true : false,
          'sale_ok' => isset($data['sale_ok']) ? true : false,
          'purchase_ok' => isset($data['purchase_ok']) ? true : false
        ];
        
        // Ajouter les champs optionnels s'ils sont définis
        if (!empty($data['uom_id'])) {
          $createData['uom_id'] = (int)$data['uom_id'];
        }
        
        if (!empty($data['uom_po_id'])) {
          $createData['uom_po_id'] = (int)$data['uom_po_id'];
        }
        
        if (!empty($data['currency_id'])) {
          $createData['currency_id'] = (int)$data['currency_id'];
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
    $result = $service->getOdooModelData('product.template', [['id', '=', $id]]);
    
    if (!$result) {
      throw $this->createNotFoundException('Produit non trouvé');
    }
    
    return $this->render('odoo/inventory_info.html.twig', [
      'result' => $result[0]
    ]);
  }
  
  #[Route('/odoo_inventory/update/{id}', name: 'odoo_inventory_update', requirements: ['id' => '\d+'])]
  public function updateInventory(Request $request, OdooService $service, int $id): Response
  {
    $product = $service->getOdooModelData('product.template', [['id', '=', $id]]);
    
    if (empty($product)) {
      $this->addFlash('error', 'Produit non trouvé');
      return $this->redirectToRoute('odoo_inventory');
    }
    
    // Pour l'instant, redirigez simplement vers la vue d'information
    return $this->redirectToRoute('odoo_inventory_info', ['id' => $id]);
  }

  #[Route('/odoo_partner/update/{id}', name: 'odoo_partner_update', requirements: ['id' => '\d+'])]
  public function updatePartner(Request $request, OdooService $service, int $id): Response
  {
    // Récupérer les données du partenaire
    $partner = $service->getOdooModelData('res.partner', [['id', '=', $id]]);
    
    if (empty($partner)) {
        $this->addFlash('error', 'Partenaire non trouvé');
        return $this->redirectToRoute('odoo_partners');
    }
    
    // Récupération des pays pour la liste déroulante
    $countryList = $service->getOdooModelData(
        'res.country',
        [],
        ['id', 'name'],
        ['order' => 'name ASC']
    );
    
    if ($request->isMethod('POST')) {
        try {
            $data = $request->request->all();
            
            // Validation des champs requis
            if (empty($data['name'])) {
                $this->addFlash('error', 'Le nom du partenaire est obligatoire');
                return $this->render('odoo/update_partner.html.twig', [
                    'partner' => $partner[0],
                    'countryList' => $countryList
                ]);
            }
            
            // Préparation des données pour la mise à jour
            $updateData = [
                'name' => $data['name'],
                'email' => $data['email'] ?? '',
                'phone' => $data['phone'] ?? '',
                'mobile' => $data['mobile'] ?? '',
                'street' => $data['street'] ?? '',
                'city' => $data['city'] ?? '',
                'zip' => $data['zip'] ?? '',
                'website' => $data['website'] ?? '',
                'vat' => $data['vat'] ?? '',
                'comment' => $data['comment'] ?? ''
            ];
            
            // Ajout du pays s'il est défini
            if (!empty($data['country_id'])) {
                $updateData['country_id'] = (int)$data['country_id'];
            }
            
            // Mise à jour de l'enregistrement
            $success = $service->updateOdoo('res.partner', $id, $updateData);
            
            if ($success) {
                $this->addFlash('success', 'Partenaire mis à jour avec succès');
                return $this->redirectToRoute('odoo_partners');
            } else {
                $this->addFlash('error', 'Échec de la mise à jour du partenaire');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la mise à jour: ' . $e->getMessage());
        }
    }
    
    return $this->render('odoo/update_partner.html.twig', [
        'partner' => $partner[0],
        'countryList' => $countryList
    ]);
}

#[Route('/odoo_partner/delete/{id}', name: 'odoo_partner_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
public function deletePartner(OdooService $service, int $id): Response
{
    try {
        // Vérifier si le partenaire existe
        $partner = $service->getOdooModelData('res.partner', [['id', '=', $id]]);
        
        if (empty($partner)) {
            $this->addFlash('error', 'Partenaire non trouvé');
            return $this->redirectToRoute('odoo_partners');
        }
        
        // Suppression du partenaire
        $success = $service->deleteOdoo('res.partner', $id);
        
        if ($success) {
            $this->addFlash('success', 'Partenaire supprimé avec succès');
        } else {
            $this->addFlash('error', 'Échec de la suppression du partenaire');
        }
    } catch (\Exception $e) {
        $this->addFlash('error', 'Erreur lors de la suppression: ' . $e->getMessage());
    }
    
    return $this->redirectToRoute('odoo_partners');
}
}
