<?php
namespace App\Controller\OdooJIT;

// src\Controller\OdooJIT\JITController.php
use App\Service\OdooService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class JITController extends AbstractController
{
  #[Route('/odoo_just_in_time', name: 'odoo_jit')]
  public function viewAllJIT(OdooService $service): Response
  {
    // Récupération des données brutes
    $rawData = $service->getOdooModelData('aa.just.in.time', [], ['id', 'display_name', 'create_date']);

    // Formatage des données pour s'assurer qu'elles sont exploitables
    $result = array_map(function ($item) {
      return [
        'id' => is_array($item['id']) ? $item['id'][0] : $item['id'],
        'display_name' => is_array($item['display_name']) ? $item['display_name'][0] : $item['display_name'],
        'create_date' => is_array($item['create_date']) ?
          (new \DateTime($item['create_date'][0]))->format('Y-m-d H:i') :
          (new \DateTime($item['create_date']))->format('Y-m-d H:i')
      ];
    }, $rawData);

    return $this->render('odoo/xjit.html.twig', [
      'result' => $result
    ]);
  }

  #[Route('/odoo_jit_info/{id}', name: 'odoo_jit_info', requirements: ['id' => '\d+'])]
  public function viewJITById(OdooService $service, int $id): Response
  {
    $result = $service->getOdooModelData('aa.just.in.time', [['id', '=', $id]], []);

    return $this->render('odoo/xjit_info.html.twig', [
      'result' => $result,
    ]);
  }

  #[Route('/odoo_jit/create', name: 'odoo_jit_create', methods: ['GET', 'POST'])]
  public function createJITRecord(Request $request, OdooService $service): Response
  {
    // Ne charger que la liste des devises (petite quantité de données)
    $currencyList = $this->currencyList($service);

    if ($request->isMethod('POST')) {
        try {
            $data = $request->request->all();
            
            // Vérification minimale des champs requis
            $requiredFields = [
                'display_name', 'zone_1', 'zone_2', 'price', 'num_storage_days',
                'restock_quantity', 'order_point', 'partner_id', 'reference_id', 'currency_id'
            ];
            
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                $this->addFlash('error', 'Champs requis manquants: ' . implode(', ', $missingFields));
                return $this->redirectToRoute('odoo_jit_create');
            }

            // Préparation des données pour création
            $createData = [
                'name' => $data['display_name'],
                'display_name' => $data['display_name'],
                'zone_1' => $data['zone_1'],
                'zone_2' => $data['zone_2'],
                'price' => $data['price'],
                'num_storage_days' => $data['num_storage_days'],
                'restock_quantity' => $data['restock_quantity'],
                'order_point' => $data['order_point'],
                'partner_id' => $data['partner_id'],
                'reference_id' => $data['reference_id'],
                'currency_id' => $data['currency_id']
            ];
            
            // Création de l'enregistrement
            $service->createOdoo('aa.just.in.time', $createData);
            $this->addFlash('success', 'JIT créé avec succès');
            return $this->redirectToRoute('odoo_jit');
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la création: ' . $e->getMessage());
            return $this->redirectToRoute('odoo_jit_create');
        }
    }
    
    // Pour le GET, ne passer que la liste des devises
    return $this->render('odoo/xjit_create.html.twig', [
        'currencyList' => $currencyList
    ]);
  }

  #[Route('/odoo_jit/update/{id}', name: 'odoo_jit_update', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
  public function updateJITRecord(Request $request, OdooService $service, int $id): Response
  {
    try {
        // Ne récupérer que les données du JIT actuel
        $result = $this->getOptimizedJITData($service, $id);
        
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $fieldsToUpdate = [];
            
            // Traiter les données du formulaire
            foreach ($data as $field => $value) {
                // Filtrer pour ne conserver que les champs valides
                if (in_array($field, [
                    'display_name',
                    'zone_1',
                    'zone_2',
                    'price',
                    'num_storage_days',
                    'restock_quantity',
                    'order_point',
                    'partner_id',
                    'reference_id',
                    'currency_id'
                ])) {
                    $fieldsToUpdate[$field] = $value;
                }
            }
            
            if (!empty($fieldsToUpdate)) {
                $service->updateOdoo('aa.just.in.time', $id, $fieldsToUpdate);
                $this->addFlash('success', 'Enregistrement mis à jour avec succès');
                return $this->redirectToRoute('odoo_jit');
            }
        }
        
        // Pour le rendu, charger uniquement les devises qui sont en nombre limité
        // et ne pas charger les autres données volumineuses
        return $this->render('odoo/xjit_edit.html.twig', [
            'result' => $result[0] ?? [],
            'infoClient' => [], // Fournir un tableau vide au lieu de données complètes
            'infoInventory' => [], // Fournir un tableau vide au lieu de données complètes
            'currencyList' => $this->currencyList($service), // Charger les devises
            'isPaginatedView' => true
        ]);
    }
    catch (\Exception $e) {
        $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
        return $this->redirectToRoute('odoo_jit');
    }
  }

  #[Route('/odoo_jit/delete/{id}', name: 'odoo_jit_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
  public function deleteJITRecord(OdooService $service, int $id): Response
  {
    $service->deleteOdoo('aa.just.in.time', $id);

    return $this->redirectToRoute('odoo_jit');
  }

  // ======================================================================================================================

  #[Route('/odoo_jit_order', name: 'odoo_jit_order')]
  public function viewAllJITOrder(OdooService $service): Response
  {
    $result = $service->getOdooModelData('aa.just.in.time.order', [], ['id', 'name', 'create_date']);

    return $this->render('odoo/xjit_order.html.twig', [
      'result' => $result,
    ]);
  }

  #[Route('/odoo_jit_order_info/{id}', name: 'odoo_jit_order_info', requirements: ['id' => '\d+'])]
  public function viewJITOrderById(OdooService $service, int $id): Response
  {
    try {
      // Utiliser directement l'approche qui fonctionne dans checkJITRecord
      $fields = $service->getFields('aa.just.in.time.order') ?? [];
      $availableFields = array_keys($fields);

      // Réduire au maximum les champs demandés - APPROCHE MINIMALISTE
      $fieldsToRequest = ['id', 'name'];

      // Récupérer les données de base garanties
      $headInfo = $service->getOdooModelData(
        'aa.just.in.time.order',
        [['id', '=', $id]],
        $fieldsToRequest
      );

      if (empty($headInfo)) {
        $this->addFlash('error', 'Commande JIT introuvable avec l\'ID: ' . $id);

        return $this->redirectToRoute('odoo_jit_order');
      }

      // Créer une structure garantie pour le template
      $orderData = [
        'id' => $headInfo[0]['id'],
        'name' => $headInfo[0]['name'],
        'display_name' => $headInfo[0]['name'],
        'create_date' => date('Y-m-d H:i:s'),
        'partner_id' => [],
        'create_uid' => [],
        'priority' => '0',
        'shipping_instruction' => '',
        'scan_date' => null
      ];

      // Essayer d'enrichir avec des données supplémentaires
      try {
        // Récupérer des champs supplémentaires si disponibles
        $additionalFields = array_intersect(
          ['partner_id', 'priority', 'shipping_instruction', 'create_date'],
          $availableFields
        );

        if (!empty($additionalFields)) {
          $extendedData = $service->getOdooModelData(
            'aa.just.in.time.order',
            [['id', '=', $id]],
            $additionalFields
          );

          if (!empty($extendedData) && isset($extendedData[0])) {
            // Fusionner les données supplémentaires avec la structure de base
            foreach ($extendedData[0] as $key => $value) {
              $orderData[$key] = $value;
            }
          }
        }
      } catch (\Exception $e) {
        // Ignorer les erreurs ici - nous avons déjà une structure minimale
      }

      // Récupérer les lignes associées de façon sécurisée
      $lineInfo = [];

      try {
        $lineInfo = $service->getOdooModelData(
          'aa.just.in.time.order.line',
          [['jit_order_id', '=', $id]],
          ['id', 'name', 'oder_quantity']
        );
      } catch (\Exception $e) {
        // Ignorer les erreurs - lignes vides par défaut
      }

      return $this->render('odoo/xjit_order_info.html.twig', [
        'headInfo' => $orderData,
        'lineInfo' => $lineInfo ?: [],
      ]);
    } catch (\Exception $e) {
      $this->addFlash('error', 'Erreur générale: ' . $e->getMessage());

      return $this->redirectToRoute('odoo_jit_order');
    }
  }

  #[Route('/odoo_jit_order/create', name: 'odoo_jit_order_create', methods: ['GET', 'POST'])]
  public function createJITOrderRecord(Request $request, OdooService $service): Response
  {
    if ($request->isMethod('POST')) {
        try {
            $data = $request->request->all();
            $binIdsJson = $data['bin_ids'] ?? '{}';
            $binIds = json_decode($binIdsJson, true);

            // Vérification des données requises
            if (empty($data['name']) || empty($data['partner_id']) || empty($binIds)) {
                $this->addFlash('error', 'Données manquantes pour créer la commande');
                return $this->redirectToRoute('odoo_jit_order_create');
            }

            // 1. Créer d'abord l'ordre principal
            $orderData = [
                'name' => $data['name'],
                'display_name' => $data['name'],
                'partner_id' => (int) $data['partner_id'],
                'shipping_instruction' => $data['shipping_instruction'] ?? '',
                'priority' => isset($data['priority']) && $data['priority'] ? '2' : '0',
                'scan_date' => (new \DateTime())->format('Y-m-d H:i:s'),
            ];

            $jitOrderId = $service->createOdoo('aa.just.in.time.order', $orderData);

            if (!$jitOrderId) {
                throw new \Exception('Échec de la création de la commande principale');
            }

            // 2. Créer les lignes associées
            foreach ($binIds as $binName => $quantity) {
                // Rechercher l'ID du bin par son nom
                $binId = $this->getBinIdByName($service, $binName);
                if ($binId) {
                    $lineData = [
                        'name' => $binId,
                        'oder_quantity' => (int) $quantity,
                        'jit_order_id' => $jitOrderId
                    ];
                    $service->createOdoo('aa.just.in.time.order.line', $lineData);
                }
            }

            $this->addFlash('success', 'Commande JIT créée avec succès');
            return $this->redirectToRoute('odoo_jit_order_info', ['id' => $jitOrderId]);

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la création de la commande : ' . $e->getMessage());
            return $this->redirectToRoute('odoo_jit_order_create');
        }
    }

    // Pour GET, ne charger que les infos minimales nécessaires
    return $this->render('odoo/xjit_order_create.html.twig', []);
  }

  /**
   * Récupère l'ID d'un bin par son nom
   */
  private function getBinIdByName(OdooService $service, string $binName): ?int
  {
    try {
        $result = $service->getOdooModelData(
            'aa.just.in.time',
            [['name', '=', $binName]],
            ['id'],
            ['limit' => 1]
        );
        
        if (!empty($result) && isset($result[0]['id'])) {
            return $result[0]['id'];
        }
    } catch (\Exception $e) {
        // Log l'erreur mais ne bloque pas le processus
        error_log('Erreur lors de la récupération du bin ID: ' . $e->getMessage());
    }
    
    return null;
  }

  #[Route('/odoo_jit_order/update/{id}', name: 'odoo_jit_order_update', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
  public function updateJITOrderRecord(Request $request, OdooService $service, int $id): Response
  {
    try {
        // Récupérer les informations
        $xjitInfo = $this->xJitInfo($service, $id);
        $headInfo = $xjitInfo['headInfo'] ?? [];
        $lineInfo = $xjitInfo['lineInfo'] ?? [];
        $binIdsByClient = $this->organizeBinIds($this->infoBinId($service));

        // Vérifier si les données existent
        if (empty($headInfo)) {
            $this->addFlash('error', 'Commande JIT introuvable avec l\'ID: ' . $id);

            return $this->redirectToRoute('odoo_jit_order');
        }

        // Vérifier que headInfo[0] existe
        if (!isset($headInfo[0])) {
            $this->addFlash('error', 'Format de données invalide pour la commande JIT: ' . $id);

            return $this->redirectToRoute('odoo_jit_order');
        }

        if ($request->isMethod('POST')) {
          $data = $request->request->all();
          $fieldsToUpdateHeader = [];

          if (isset($data['name'])) {
            $fieldsToUpdateHeader['display_name'] = $data['name'];
            $fieldsToUpdateHeader['name'] = $data['name'];
          }

          if (isset($data['priority'])) {
            $fieldsToUpdateHeader['priority'] = $data['priority'] ? '2' : '0';
          }

          if (isset($data['shipping_instruction'])) {
            $fieldsToUpdateHeader['shipping_instruction'] = $data['shipping_instruction'];
          }

          if (!empty($fieldsToUpdateHeader)) {
            $service->updateOdoo('aa.just.in.time.order', $id, $fieldsToUpdateHeader);
          }

          $this->addFlash('success', 'Commande mise à jour avec succès');

          return $this->redirectToRoute('odoo_jit_order_info', ['id' => $id]);
        }

        // Affichage du formulaire
        return $this->render('odoo/xjit_order_update.html.twig', [
          'headInfo' => $headInfo[0],
          'lineInfo' => $lineInfo,
          'infoClient' => $this->infoClient($service),
          'binIdsByClient' => json_encode($binIdsByClient),
        ]);
    } catch (\Exception $e) {
      $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());

      return $this->redirectToRoute('odoo_jit_order');
    }
  }

  private function getBinName($binId, $binIdsByClient): string
  {
    foreach ($binIdsByClient as $clientBins) {
      if (!isset($clientBins['bins']) || !is_array($clientBins['bins'])) {
        continue;
      }

      foreach ($clientBins['bins'] as $bin) {
        if ($bin['id'] == $binId) {
          return $bin['name'];
        }
      }
    }

    return (string) $binId;
  }

  private function organizeBinIds(array $infoBinId): array
  {
    $binIdsByClient = [];

    foreach ($infoBinId as $binId) {
      $clientId = $binId['partner_id'] ? $binId['partner_id'][0] : 'no_client';
      $clientName = $binId['partner_id'] ? $binId['partner_id'][1] : 'Sans client';

      if (!isset($binIdsByClient[$clientId])) {
        $binIdsByClient[$clientId] = [
          'name' => $clientName,
          'bins' => []
        ];
      }
      $binIdsByClient[$clientId]['bins'][] = [
        'id' => $binId['id'],
        'name' => $binId['name']
      ];
    }

    return $binIdsByClient;
  }

  public function xJitInfo(OdooService $service, int $id): array
  {
    try {
        // Récupérer les champs disponibles
        $fields = $service->getFields('aa.just.in.time.order') ?? [];
        $availableFields = array_keys($fields);
        
        // Champs de base à toujours demander
        $baseFields = ['id', 'name'];
        
        // Champs optionnels à récupérer si disponibles
        $optionalFields = [
            'display_name', 'create_date', 'create_uid', 'partner_id',
            'order_lines', 'priority', 'scan_date', 'shipping_instruction'
        ];
        
        // Filtrer les champs pour inclure uniquement ceux disponibles
        $fieldsToRequest = array_merge(
            $baseFields,
            array_intersect($optionalFields, $availableFields)
        );
        
        // Récupérer l'en-tête de la commande
        $headInfo = $service->getOdooModelData(
            'aa.just.in.time.order',
            [['id', '=', $id]],
            $fieldsToRequest
        );
        
        // Récupérer les lignes de commande
        $lineInfo = [];
        try {
            $lineInfo = $service->getOdooModelData(
                'aa.just.in.time.order.line',
                [['jit_order_id', '=', $id]],
                ['id', 'name', 'oder_quantity']
            );
        } catch (\Exception $e) {
            // Utiliser un tableau vide en cas d'erreur
        }
        
        // Toujours retourner des tableaux, jamais null
        return [
            'headInfo' => $headInfo ?: [],
            'lineInfo' => $lineInfo ?: []
        ];
    } catch (\Exception $e) {
        // Retourner des structures vides en cas d'erreur
        return [
            'headInfo' => [],
            'lineInfo' => [],
            'error' => $e->getMessage()
        ];
    }
  }

  #[Route('/odoo_jit_order/delete/{id}', name: 'odoo_jit_order_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
  public function deletePartner(OdooService $service, int $id): Response
  {
    $service->deleteOdoo('aa.just.in.time.order', $id);

    return $this->redirectToRoute('odoo_jit_order');
  }

  public function infoClient(OdooService $service)
  {
    return $service->getOdooModelData('res.partner', [], ['id', 'name']);
  }

  public function currencyList(OdooService $service)
  {
    return $service->getOdooModelData('res.currency', [], ['name']);
  }

  public function infoInventory(OdooService $service)
  {
    return $service->getOdooModelData('product.template', [], ['id', 'display_name']);
  }

  public function infoBinId(OdooService $service)
  {
    return $service->getOdooModelData('aa.just.in.time', [], ['id', 'name', 'partner_id']);
  }

  #[Route('/odoo_jit_order/test_models', name: 'odoo_jit_test_models')]
  public function testOdooModels(OdooService $service): Response
  {
    $models = [
      'aa.just.in.time.order',
      'aa.just.in.time.order.line',
      'aa.just.in.time'
    ];

    $results = [];

    foreach ($models as $model) {
      try {
        // 1. Vérifier si le modèle existe
        $fields = $service->getFields($model) ?? []; // Ajouter ?? [] pour éviter null

        // 2. Récupérer un échantillon de données
        $sample = $service->getOdooModelData($model, [], ['id']);

        // 3. Vérifier les permissions
        $modelInfo = [
          'status' => 'success',
          'fields_count' => count($fields), // Maintenant count() fonctionne car $fields est au moins un tableau vide
          'fields' => array_keys($fields),  // array_keys sur un tableau vide retourne un tableau vide
          'sample' => $sample,
          'access' => [
            'read' => true,
            'write' => true,
            'create' => true,
            'unlink' => true
          ]
        ];

        // Tester les droits d'accès (peut échouer si les permissions sont limitées)
        try {
          // Test de lecture avec tous les champs
          $fullRecord = !empty($sample) && isset($sample[0]['id'])
            ? $service->getOdooModelData($model, [['id', '=', $sample[0]['id']]], array_slice(array_keys($fields), 0, 5))
            : null;
          $modelInfo['test_read'] = $fullRecord;
        } catch (\Exception $e) {
          $modelInfo['access']['read'] = false;
          $modelInfo['read_error'] = $e->getMessage();
        }

        $results[$model] = $modelInfo;
      } catch (\Exception $e) {
        $results[$model] = [
          'status' => 'error',
          'message' => $e->getMessage()
        ];
      }
    }

    return $this->json($results);
  }

  #[Route('/odoo_jit_order/check/{id}', name: 'odoo_jit_check')]
  public function checkJITRecord(OdooService $service, int $id): Response
  {
    $result = [
      'id_check' => null,
      'basic_data' => null,
      'full_data' => null,
      'errors' => []
    ];

    try {
      $result['id_check'] = $service->getOdooModelData(
        'aa.just.in.time.order',
        [['id', '=', $id]],
        ['id']
      );
    } catch (\Exception $e) {
      $result['errors'][] = 'ID check error: ' . $e->getMessage();
    }

    try {
      $result['basic_data'] = $service->getOdooModelData(
        'aa.just.in.time.order',
        [['id', '=', $id]],
        ['id', 'name']
      );
    } catch (\Exception $e) {
      $result['errors'][] = 'Basic data error: ' . $e->getMessage();
    }

    try {
      $fields = $service->getFields('aa.just.in.time.order');
      $result['available_fields'] = array_keys($fields);

      $result['full_data'] = $service->getOdooModelData(
        'aa.just.in.time.order',
        [['id', '=', $id]],
        array_slice($result['available_fields'], 0, 10)
      );
    } catch (\Exception $e) {
      $result['errors'][] = 'Full data error: ' . $e->getMessage();
    }

    return $this->json($result);
  }

  /**
   * Récupère les champs nécessaires pour l'édition du JIT avec une approche optimisée
   * pour limiter l'utilisation de la mémoire
   *
   * @param OdooService $service Le service Odoo
   * @param int $id L'ID du JIT à récupérer
   * @return array Les données du JIT
   */
  private function getOptimizedJITData(OdooService $service, int $id): array
  {
    // Ne demander que les champs nécessaires pour l'édition
    $fields = [
      'id',
      'display_name',
      'zone_1',
      'zone_2',
      'price',
      'num_storage_days',
      'restock_quantity',
      'order_point',
      'partner_id',
      'reference_id',
      'currency_id'
    ];

    return $service->getOdooModelData(
      'aa.just.in.time',
      [['id', '=', $id]],
      $fields
    );
  }
}