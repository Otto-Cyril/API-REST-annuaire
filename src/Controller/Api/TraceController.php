<?php

namespace App\Controller\Api;

use App\Repository\TraceRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/traces')]
class TraceController extends AbstractApiController
{
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 200;

    public function __construct(
        private readonly TraceRepository $repository,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    protected function getSerializer(): SerializerInterface
    {
        return $this->serializer;
    }

    protected function getValidator(): ValidatorInterface
    {
        return $this->validator;
    }

    /**
     * Liste paginée du journal d'audit, les plus récentes d'abord.
     * ROLE_ADMIN requis. Paramètres : page (défaut 1), limit (défaut 50, max 200) ; en-têtes X-Total-Count, X-Page, X-Per-Page, X-Total-Pages.
     */
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        [$page, $limit] = $this->pagination($request, self::DEFAULT_LIMIT, self::MAX_LIMIT);

        return $this->paginatedResponse($this->repository->paginate($page, $limit), $page, $limit, ['trace:read']);
    }

    /**
     * Renvoie une trace d'audit par id. ROLE_ADMIN requis ; 404 si introuvable.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return $this->jsonResource($this->findOrFail($this->repository, $id), Response::HTTP_OK, ['trace:read']);
    }
}
