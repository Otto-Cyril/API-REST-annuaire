<?php

namespace App\Controller\Api;

use App\Entity\Service;
use App\Repository\ServiceRepository;
use App\Service\TraceLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/services')]
class ServiceController extends AbstractApiController
{
    public function __construct(
        private readonly ServiceRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly TraceLogger $traceLogger,
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
        return $this->jsonResource($this->repository->findAll(), Response::HTTP_OK, ['service:read']);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return $this->jsonResource($this->findOrFail($this->repository, $id), Response::HTTP_OK, ['service:read']);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $service = $this->deserialize($request, Service::class, ['service:write']);
        $this->validateOrFail($service);

        $this->entityManager->persist($service);
        $this->entityManager->flush();
        $this->traceLogger->log(sprintf('Création du service #%d', $service->getId()));

        return $this->jsonResource($service, Response::HTTP_CREATED, ['service:read']);
    }

    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $service = $this->findOrFail($this->repository, $id);
        $this->deserialize($request, Service::class, ['service:write'], $service);
        $this->validateOrFail($service);

        $this->entityManager->flush();
        $this->traceLogger->log(sprintf('Modification du service #%d', $service->getId()));

        return $this->jsonResource($service, Response::HTTP_OK, ['service:read']);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $service = $this->findOrFail($this->repository, $id);

        $this->entityManager->remove($service);
        $this->entityManager->flush();
        $this->traceLogger->log(sprintf('Suppression du service #%d', $id));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
