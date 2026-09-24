<?php

namespace App\Controller\Api;

use App\Repository\TraceRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/traces')]
class TraceController extends AbstractApiController
{
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

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->jsonResource($this->repository->findAll(), Response::HTTP_OK, ['trace:read']);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return $this->jsonResource($this->findOrFail($this->repository, $id), Response::HTTP_OK, ['trace:read']);
    }
}
