<?php

namespace App\Controller;

use App\Entity\Manual;
use App\Repository\ManualRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ManualManagmentController extends AbstractController
{

    #[Route('/manual/managment', name: 'app_manual_managment')]
    public function index(
        Request $request,
        ManualRepository $manualRepository
    ): Response {
        $searchTerm = $request->query->get('search', '');
        $page = $request->query->getInt('page', 1);
        $itemsPerPage = 10; // Or make this configurable

        $paginator = $manualRepository->searchManualsPaginated(
            searchTerm: $searchTerm,
            sortBy: 'timestamp',
            sortOrder: 'DESC',
            page: $page,
            itemsPerPage: $itemsPerPage
        );

        return $this->render('admin/manual_managment/index.html.twig', [
            'paginator' => $paginator,
            'searchTerm' => $searchTerm
        ]);
    }


    #[Route('/{hash}', name: 'app_manual_view')]
    public function viewManual(
        string $hash,
        EntityManagerInterface $entityManager,
        Request $request,
    ): Response {
        $manual = $entityManager->getRepository(Manual::class)->findOneBy(['keyword' => $hash]);

        if (!$manual) {
            throw $this->createNotFoundException('Manual not found');
        }

        $manual->setClicks($manual->getClicks() + 1);
        $entityManager->flush();

        try {
            $httpClient = HttpClient::create();
            $response = $httpClient->request('GET', $manual->getUrl());
            $content = $response->getContent();

            return new Response($content, Response::HTTP_OK, [
                'Content-Type' => $response->getHeaders()['content-type'][0] ?? '
                text/html'
            ]);
        } catch (\Exception $e) {
            // Add error message to flash bag
            $request->getSession()->getFlashBag()->add('error', 'Failed to load content: ' . $e->getMessage());
            return $this->redirectToRoute('app_manual_managment');
        }
    }

    #[Route('/manuals/bulk-delete', name: 'app_manuals_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request, EntityManagerInterface $entityManager): Response
    {
        $manualIds = $request->request->all('manualIds');

        if (!empty($manualIds)) {
            $repository = $entityManager->getRepository(Manual::class);
            foreach ($manualIds as $id) {
                $manual = $repository->find($id);
                if ($manual) {
                    $entityManager->remove($manual);
                }
            }
            $entityManager->flush();
            $this->addFlash('success', 'Selected manuals have been deleted.');
        } else {
            $this->addFlash('error', 'No manuals selected for deletion.');
        }

        return $this->redirectToRoute('app_manual_managment');
    }
}
