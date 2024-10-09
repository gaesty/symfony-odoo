<?php
// src/Controller/LuckyController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LuckyController extends AbstractController
{
  #[Route('/lucky/number', name: 'lucky')]
  public function number(): Response
  {
    $number = random_int(0, 100);

    return $this->render('lucky/number.html.twig', [
      'number' => $number,
    ]);
  }

  #[Route('/blog/{page}', name: 'blog_list', requirements: ['page' => '\d+'])]
  public function list(int $page = 1): Response
  {
    // generate a URL with no route arguments
    $signUpPage = $this->generateUrl('lucky');

    return $this->render('blog/list.html.twig', [
      'signUpPage' => $signUpPage,
      'page'       => $page,
    ]);
  }

  // #[Route('/github', name: 'github')]
  // public function github(HttpService $service): Response
  // {
  //   $result           = $service->fetchGitHubInformation();
  //   return $this->render('odoo/index.html.twig', [
  //     'result'      => $result,
  //   ]);
  // }
}
