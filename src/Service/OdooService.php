<?php
namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

class OdooService
{
  private HttpService $httpService;
  private string $odooUrl;
  private string $odooDb;
  private string $odooUser;
  private string $odooPassword;

  private RequestStack $requestStack;

  public function __construct(
    HttpService $httpService,
    RequestStack $requestStack,
    string $odooUrl,
    string $odooDb,
    string $odooUser,
    string $odooPassword
  ) {
    $this->httpService = $httpService;
    $this->requestStack = $requestStack;
    $this->odooUrl = $odooUrl;
    $this->odooDb = $odooDb;
    $this->odooUser = $odooUser;
    $this->odooPassword = $odooPassword;
  }

  private function getSession()
  {
    return $this->requestStack->getCurrentRequest()->getSession();
  }

  private function odooAuthenticate()
  {
    $session = $this->getSession();

    if ($session->has('odoo_uid')) {
      return $session->get('odoo_uid');
    }
    $uid = $this->httpService->odooCall($this->odooUrl, 'common', 'authenticate', [
      $this->odooDb,
      $this->odooUser,
      $this->odooPassword,
      []
    ]);
    $session->set('odoo_uid', $uid);

    return $uid;
  }

  public function getOdooModelData($model, $domain = [], $fields = [], $options = [])
  {
    $params = [];

    // Ajouter les champs s'ils sont fournis
    if (!empty($fields)) {
      $params['fields'] = $fields;
    }

    // Ajouter les options de pagination
    if (isset($options['limit'])) {
      $params['limit'] = $options['limit'];
    }

    if (isset($options['offset'])) {
      $params['offset'] = $options['offset'];
    }

    // Ajouter l'ordre si spécifié
    if (isset($options['order'])) {
      $params['order'] = $options['order'];
    }

    try {
      $result = $this->httpService->odooCall($this->odooUrl, 'object', 'execute_kw', [
        $this->odooDb,
        $this->odooAuthenticate(),
        $this->odooPassword,
        $model,
        'search_read',
        [$domain],
        $params
      ]);

      // Garantir que la méthode renvoie toujours un tableau
      return is_array($result) ? $result : [];
    } catch (\Exception $e) {
      error_log("Erreur dans getOdooModelData: " . $e->getMessage());
      // Retourner un tableau vide en cas d'erreur plutôt que null
      return [];
    }
  }

  public function createOdoo(string $model, array $Data): int
  {
    return $this->httpService->odooCall($this->odooUrl, 'object', 'execute_kw', [
      $this->odooDb,
      $this->odooAuthenticate(),
      $this->odooPassword,
      $model,
      'create',
      [$Data]
    ]);
  }

  public function updateOdoo(string $model, int $Id, array $fieldsToUpdate): bool
  {
    return $this->httpService->odooCall($this->odooUrl, 'object', 'execute_kw', [
      $this->odooDb,
      $this->odooAuthenticate(),
      $this->odooPassword,
      $model,
      'write',
      [[$Id], $fieldsToUpdate]
    ]);
  }

  public function deleteOdoo(string $model, int $Id): bool
  {
    return $this->httpService->odooCall($this->odooUrl, 'object', 'execute_kw', [
      $this->odooDb,
      $this->odooAuthenticate(),
      $this->odooPassword,
      $model,
      'unlink',
      [[$Id]]
    ]);
  }

  public function getFields($model)
  {
    return $this->httpService->odooCall($this->odooUrl, 'object', 'execute_kw', [
      $this->odooDb,
      $this->odooAuthenticate(),
      $this->odooPassword,
      $model,
      'fields_get',
      []
    ]);
  }

  public function createOdooMultiple(string $model, array $data, bool $multiple = false): int|array
  {
    $args = $multiple ? [$data] : [$data];
    $response = $this->httpService->odooCallMultiple($this->odooUrl, 'object', 'execute_kw', [
      $this->odooDb,
      $this->odooAuthenticate(),
      $this->odooPassword,
      $model,
      'create',
      $args
    ]);

    if ($multiple) {
      if (!is_array($response)) {
        throw new \RuntimeException("Odoo did not return an array of IDs for model {$model}");
      }

      return $response;
    } else {
      if (!is_int($response)) {
        throw new \RuntimeException("Odoo did not return an ID for model {$model}");
      }

      return $response;
    }
  }
  public function deleteOdooMultiple(string $model, array $ids): bool
  {
    try {
      // Convertir le tableau en recordset avant de faire unlink
      $response = $this->httpService->odooCall(
        $this->odooUrl,
        'object',
        'execute_kw',
        [
          $this->odooDb,
          $this->odooAuthenticate(),
          $this->odooPassword,
          $model,
          'search',
          [[['id', 'in', array_values($ids)]]]
        ]
      );

      if (empty($response)) {
        return true;
      }

      return $this->httpService->odooCall(
        $this->odooUrl,
        'object',
        'execute_kw',
        [
          $this->odooDb,
          $this->odooAuthenticate(),
          $this->odooPassword,
          $model,
          'unlink',
          [$response]
        ]
      ) ?? false;
    } catch (\Exception $e) {
      throw new \RuntimeException("Erreur lors de la suppression multiple dans {$model}: " . $e->getMessage());
    }
  }

  /**
   * Compte le nombre total d'enregistrements d'un modèle qui correspondent à un domaine
   * 
   * @param string $model Nom du modèle Odoo
   * @param array $domain Filtres (domaine) à appliquer
   * @param array $context Contexte supplémentaire pour la requête (optionnel)
   * @return int Nombre d'enregistrements
   */
  public function countOdooModelData(string $model, array $domain = [], array $context = []): int
  {
    $options = [];
    if (!empty($context)) {
      $options['context'] = $context;
    }

    return $this->httpService->odooCall($this->odooUrl, 'object', 'execute_kw', [
      $this->odooDb,
      $this->odooAuthenticate(),
      $this->odooPassword,
      $model,
      'search_count',
      [$domain],
      $options
    ]);
  }

  /**
   * @deprecated Utilisez countOdooModelData() à la place
   */
  public function countOdooModelDataWithContext($model, $context = [])
  {
    return $this->countOdooModelData($model, $context);
  }

  /**
   * Recherche les IDs des enregistrements d'un modèle Odoo
   * 
   * @param string $model Nom du modèle Odoo
   * @param array $domain Filtres (domaine) à appliquer
   * @param array $options Options supplémentaires (limit, offset, order)
   * @return array Liste des IDs trouvés
   */
  public function searchOdooModelIds(string $model, array $domain = [], array $options = []): array
  {
    $params = [];

    // Appliquer les options de pagination/tri
    if (isset($options['limit'])) {
      $params['limit'] = $options['limit'];
    }

    if (isset($options['offset'])) {
      $params['offset'] = $options['offset'];
    }

    if (isset($options['order'])) {
      $params['order'] = $options['order'];
    }

    try {
      $result = $this->httpService->odooCall(
        $this->odooUrl,
        'object',
        'execute_kw',
        [
          $this->odooDb,
          $this->odooAuthenticate(),
          $this->odooPassword,
          $model,
          'search',
          [$domain],
          $params
        ]
      );

      return is_array($result) ? $result : [];
    } catch (\Exception $e) {
      error_log("Erreur searchOdooModelIds: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Récupère les données d'un modèle par IDs
   * 
   * @param string $model Nom du modèle Odoo
   * @param array $ids Liste des IDs à récupérer
   * @param array $fields Champs à récupérer
   * @return array Données des enregistrements
   */
  public function getOdooModelById(string $model, array $ids, array $fields = []): array
  {
    if (empty($ids)) {
      return [];
    }

    $params = [];
    if (!empty($fields)) {
      $params['fields'] = $fields;
    }

    try {
      $result = $this->httpService->odooCall(
        $this->odooUrl,
        'object',
        'execute_kw',
        [
          $this->odooDb,
          $this->odooAuthenticate(),
          $this->odooPassword,
          $model,
          'read',
          [$ids],
          $params
        ]
      );

      return is_array($result) ? $result : [];
    } catch (\Exception $e) {
      error_log("Erreur getOdooModelById: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Retourne l'URL Odoo
   */
  public function getOdooUrl(): string
  {
    return $this->odooUrl;
  }

  /**
   * Retourne le nom de la base de données Odoo
   */
  public function getOdooDb(): string
  {
    return $this->odooDb;
  }

  /**
   * Retourne le mot de passe Odoo
   */
  public function getOdooPassword(): string
  {
    return $this->odooPassword;
  }

  /**
   * Effectue l'authentification et retourne l'identifiant de session
   */
  public function authenticateOdoo()
  {
    return $this->odooAuthenticate();
  }

  /**
   * Méthode wrapper pour httpService->odooCall pour les appels directs
   * 
   * @param string $url URL du serveur Odoo
   * @param string $service Nom du service Odoo à appeler
   * @param string $method Nom de la méthode à exécuter
   * @param array $params Paramètres pour la méthode
   * @return mixed Résultat de l'appel
   */
  public function callOdoo(string $url, string $service, string $method, array $params)
  {
    return $this->httpService->odooCall($url, $service, $method, $params);
  }
}
