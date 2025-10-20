<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UrlShortnerController extends AbstractController
{
    #[Route('/url/shortner', name: 'app_url_shortner')]
    public function index(): Response
    {
        return $this->render('admin/url_shortner/index.html.twig', [
            'controller_name' => 'UrlShortnerController',
        ]);
    }
}
