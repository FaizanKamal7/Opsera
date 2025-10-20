<?php

declare(strict_types=1);

namespace App\Controller\Api;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'User profile', description: 'User profile information')]
#[AsController]
class UserProfileController
{
    #[OA\Parameter(
        name: 'text',
        description: 'The text to pong back',
        in: 'query',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Successful ping',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'pong', type: 'string'),
            ],
            type: 'object',
        )
    )]
    #[Route('/ping', methods: ['GET'])]
    public function ping(#[MapQueryParameter] string $text): JsonResponse
    {
        return new JsonResponse(['pong' => $text]);
    }
}
