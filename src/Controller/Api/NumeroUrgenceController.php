<?php

namespace App\Controller\Api;

use App\Entity\NumeroUrgence;
use App\Repository\NumeroUrgenceRepository;
use App\Service\TraceLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/numeros-urgence')]
class NumeroUrgenceController extends AbstractApiController
{
    public function __construct(
        private readonly NumeroUrgenceRepository $repository,
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
        return $this->jsonResource($this->repository->findAll(), Response::HTTP_OK, ['numero_urgence:read']);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return $this->jsonResource($this->findOrFail($this->repository, $id), Response::HTTP_OK, ['numero_urgence:read']);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $numeroUrgence = $this->deserialize($request, NumeroUrgence::class, ['numero_urgence:write']);
        $this->validateOrFail($numeroUrgence);

        $this->entityManager->persist($numeroUrgence);
        $this->entityManager->flush();
        $this->traceLogger->log(sprintf('Création du numéro d\'urgence #%d', $numeroUrgence->getId()));

        return $this->jsonResource($numeroUrgence, Response::HTTP_CREATED, ['numero_urgence:read']);
    }

    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $numeroUrgence = $this->findOrFail($this->repository, $id);
        $this->deserialize($request, NumeroUrgence::class, ['numero_urgence:write'], $numeroUrgence);
        $this->validateOrFail($numeroUrgence);

        $this->entityManager->flush();
        $this->traceLogger->log(sprintf('Modification du numéro d\'urgence #%d', $numeroUrgence->getId()));

        return $this->jsonResource($numeroUrgence, Response::HTTP_OK, ['numero_urgence:read']);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $numeroUrgence = $this->findOrFail($this->repository, $id);

        $this->entityManager->remove($numeroUrgence);
        $this->entityManager->flush();
        $this->traceLogger->log(sprintf('Suppression du numéro d\'urgence #%d', $id));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
