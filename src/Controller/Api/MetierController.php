<?php

namespace App\Controller\Api;

use App\Entity\Metier;
use App\Repository\MetierRepository;
use App\Service\TraceLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/metiers')]
class MetierController extends AbstractApiController
{
    public function __construct(
        private readonly MetierRepository $repository,
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
        return $this->jsonResource($this->repository->findAll(), Response::HTTP_OK, ['metier:read']);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return $this->jsonResource($this->findOrFail($this->repository, $id), Response::HTTP_OK, ['metier:read']);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $metier = $this->deserialize($request, Metier::class, ['metier:write']);
        $this->validateOrFail($metier);

        $this->entityManager->persist($metier);
        $this->entityManager->flush();
        $this->traceLogger->log(sprintf('Création du métier #%d', $metier->getId()));

        return $this->jsonResource($metier, Response::HTTP_CREATED, ['metier:read']);
    }

    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $metier = $this->findOrFail($this->repository, $id);
        $this->deserialize($request, Metier::class, ['metier:write'], $metier);
        $this->validateOrFail($metier);

        $this->entityManager->flush();
        $this->traceLogger->log(sprintf('Modification du métier #%d', $metier->getId()));

        return $this->jsonResource($metier, Response::HTTP_OK, ['metier:read']);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $metier = $this->findOrFail($this->repository, $id);

        $this->entityManager->remove($metier);
        $this->entityManager->flush();
        $this->traceLogger->log(sprintf('Suppression du métier #%d', $id));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
