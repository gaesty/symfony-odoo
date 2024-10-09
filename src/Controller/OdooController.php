<?php
// src/Controller/OdooController.php
namespace App\Controller;

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
    $result = $service->getOdooModelData('res.partner', [], ['name', 'email', 'phone']);

    return $this->render('odoo/partners.html.twig', [
      'result' => $result,
    ]);
  }

  #[Route('/odoo_partner/{id}', name: 'odoo_partner_view', requirements: ['id' => '\d+'])]
  public function viewPartnerById(OdooService $service, int $id): Response
  {
    $partner = $service->getOdooModelData('res.partner', [['id', '=', $id]], ['name', 'email', 'phone']);

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
    if ($request->isMethod('POST')) {
      $data  = $request->request->all(); // Expecting partner data from the request

      $name  = $data['name'] ?? null;
      $email = $data['email'] ?? null;
      $phone = $data['phone'] ?? null;

      if (!$name || !$email || !$phone) {
        return $this->json(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
      }

      $service->createOdooPartner('res.partner', [
        'name'  => $name,
        'email' => $email,
        'phone' => $phone
      ]);

      return $this->redirectToRoute('odoo_partners');
    }
    else {
      return $this->render('odoo/create_partner.html.twig');
    }
  }

  #[Route('/odoo_partner/update/{id}', name: 'odoo_partner_update', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
  public function updatePartner(Request $request, OdooService $service, int $id): Response
  {
    // Fetch the partner data
    $partner = $service->getOdooModelData('res.partner', [['id', '=', $id]], ['name', 'email', 'phone']);

    if ($request->isMethod('POST')) {
      $data           = $request->request->all();

      $fieldsToUpdate = [];

      if (isset($data['name'])) {
        $fieldsToUpdate['name'] = $data['name'];
      }

      if (isset($data['email'])) {
        $fieldsToUpdate['email'] = $data['email'];
      }

      if (isset($data['phone'])) {
        $fieldsToUpdate['phone'] = $data['phone'];
      }

      if (empty($fieldsToUpdate)) {
        return $this->json(['error' => 'No fields to update'], Response::HTTP_BAD_REQUEST);
      }

      $service->updateOdooPartner('res.partner', $id, $fieldsToUpdate);

      return $this->redirectToRoute('odoo_partners');
    }
    else {
      return $this->render('odoo/edit_partner.html.twig', [
        'partner' => $partner[0]
      ]);
    }
  }

  #[Route('/odoo_partner/delete/{id}', name: 'odoo_partner_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
  public function deletePartner(OdooService $service, int $id): Response
  {
    $service->deleteOdooPartner('res.partner', $id);

    return $this->redirectToRoute('odoo_partners');
  }
}
