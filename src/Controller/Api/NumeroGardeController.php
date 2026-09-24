<?php

namespace App\Controller\Api;

use App\Entity\NumeroGarde;
use App\Repository\NumeroGardeRepository;
use App\Repository\PersonnelDeGardeRepository;
use App\Service\TraceLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/numeros-garde')]
class NumeroGardeController extends AbstractApiController
{
    public function __construct(
        private readonly NumeroGardeRepository $repository,
        private readonly PersonnelDeGardeRepository $personnelRepository,
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
        return $this->jsonResource($this->repository->findAll(), Response::HTTP_OK, ['numero_garde:read']);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return $this->jsonResource($this->findOrFail($this->repository, $id), Response::HTTP_OK, ['numero_garde:read']);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $numeroGarde = $this->deserialize($request, NumeroGarde::class, ['numero_garde:write']);
        $this->applyRelations($numeroGarde, $request);
        $this->validateOrFail($numeroGarde);

        $this->entityManager->persist($numeroGarde);
        $this->entityManager->flush();
        $this->traceLogger->log(sprintf('Création du numéro de garde #%d', $numeroGarde->getId()));

        return $this->jsonResource($numeroGarde, Response::HTTP_CREATED, ['numero_garde:read']);
    }

    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $numeroGarde = $this->findOrFail($this->repository, $id);
        $this->deserialize($request, NumeroGarde::class, ['numero_garde:write'], $numeroGarde);
        $this->applyRelations($numeroGarde, $request);
        $this->validateOrFail($numeroGarde);

        $this->entityManager->flush();
        $this->traceLogger->log(sprintf('Modification du numéro de garde #%d', $numeroGarde->getId()));

        return $this->jsonResource($numeroGarde, Response::HTTP_OK, ['numero_garde:read']);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $numeroGarde = $this->findOrFail($this->repository, $id);

        $this->entityManager->remove($numeroGarde);
        $this->entityManager->flush();
        $this->traceLogger->log(sprintf('Suppression du numéro de garde #%d', $id));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function applyRelations(NumeroGarde $numeroGarde, Request $request): void
    {
        $data = $this->requestData($request);

        if (null !== $personnel = $this->findRelation($data, 'personnelDeGardeId', $this->personnelRepository)) {
            $numeroGarde->setPersonnelDeGarde($personnel);
        }
    }
}
