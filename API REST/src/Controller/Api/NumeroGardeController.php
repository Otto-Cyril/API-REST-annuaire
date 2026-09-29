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

    /**
     * Liste des numéros de garde (sans pagination).
     * Public (GET). Renvoie 200 avec le tableau complet.
     */
    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->jsonResource($this->repository->findAll(), Response::HTTP_OK, ['numero_garde:read']);
    }

    /**
     * Renvoie le numéro de garde dont l'id est donné dans l'URL.
     * Public (GET). 200 si trouvé, 404 sinon.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return $this->jsonResource($this->findOrFail($this->repository, $id), Response::HTTP_OK, ['numero_garde:read']);
    }

    /**
     * Crée un numéro de garde à partir du corps JSON.
     * Champs modifiables : numero, type ; personnelDeGardeId (id du personnel de garde rattaché, obligatoire à la création, 400 si introuvable).
     * ROLE_ADMIN requis (JWT). 201 avec la ressource créée ; 400 si JSON/types invalides, 422 si validation échoue.
     * Enregistre une trace « Création … » dans la même transaction.
     */
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $numeroGarde = $this->deserialize($request, NumeroGarde::class, ['numero_garde:write']);
        $this->applyRelations($numeroGarde, $request);
        $this->validateOrFail($numeroGarde);

        $this->traceLogger->transactional(function () use ($numeroGarde) {
            $this->entityManager->persist($numeroGarde);
            $this->entityManager->flush();
            $this->traceLogger->log(sprintf('Création du numéro de garde #%d', $numeroGarde->getId()));
        });

        return $this->jsonResource($numeroGarde, Response::HTTP_CREATED, ['numero_garde:read']);
    }

    /**
     * Met à jour le numéro de garde d'id donné avec le corps JSON (mise à jour partielle : seuls les champs envoyés changent, PUT et PATCH sont équivalents).
     * Champs modifiables : numero, type ; personnelDeGardeId (id du personnel de garde rattaché, obligatoire à la création, 400 si introuvable).
     * ROLE_ADMIN requis (JWT). 200 avec la ressource modifiée ; 404 si introuvable ; 400 si JSON/types invalides ; 422 si validation échoue.
     * Enregistre une trace « Modification … » dans la même transaction.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $numeroGarde = $this->findOrFail($this->repository, $id);
        $this->deserialize($request, NumeroGarde::class, ['numero_garde:write'], $numeroGarde);
        $this->applyRelations($numeroGarde, $request);
        $this->validateOrFail($numeroGarde);

        $this->traceLogger->transactional(function () use ($numeroGarde) {
            $this->entityManager->flush();
            $this->traceLogger->log(sprintf('Modification du numéro de garde #%d', $numeroGarde->getId()));
        });

        return $this->jsonResource($numeroGarde, Response::HTTP_OK, ['numero_garde:read']);
    }

    /**
     * Supprime le numéro de garde d'id donné.
     * ROLE_ADMIN requis (JWT). 204 sans contenu ; 404 si introuvable ; 409 si la ressource est encore référencée ailleurs.
     * Enregistre une trace « Suppression … » dans la même transaction.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $numeroGarde = $this->findOrFail($this->repository, $id);

        $this->traceLogger->transactional(function () use ($id, $numeroGarde) {
            $this->entityManager->remove($numeroGarde);
            $this->entityManager->flush();
            $this->traceLogger->log(sprintf('Suppression du numéro de garde #%d', $id));
        });

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
