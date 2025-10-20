<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\ACL;
use App\ReadModel\UsersReadModel;
use App\Service\OpenApi\Attribute\MapReadModel;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Users', description: 'Users management')]
#[Route('/users')]
#[AsController]
#[IsGranted(ACL::ROLE_ADMIN)]
class UsersController
{
    #[Route(methods: ['GET'])]
    public function list(
        Request $request,
        #[MapReadModel] UsersReadModel $usersReadModel,
    ): JsonResponse {
        $data = $usersReadModel->handleRequest($request)->getResult();

        return new JsonResponse($data);
    }
}
