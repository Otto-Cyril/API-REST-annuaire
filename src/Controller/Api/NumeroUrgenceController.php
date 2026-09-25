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

    /**
     * Liste des numéros d'urgence (sans pagination).
     * Public (GET). Renvoie 200 avec le tableau complet.
     */
    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->jsonResource($this->repository->findAll(), Response::HTTP_OK, ['numero_urgence:read']);
    }

    /**
     * Renvoie le numéro d'urgence dont l'id est donné dans l'URL.
     * Public (GET). 200 si trouvé, 404 sinon.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return $this->jsonResource($this->findOrFail($this->repository, $id), Response::HTTP_OK, ['numero_urgence:read']);
    }

    /**
     * Crée un numéro d'urgence à partir du corps JSON.
     * Champs modifiables : libelle, numero.
     * ROLE_ADMIN requis (JWT). 201 avec la ressource créée ; 400 si JSON/types invalides, 422 si validation échoue.
     * Enregistre une trace « Création … » dans la même transaction.
     */
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $numeroUrgence = $this->deserialize($request, NumeroUrgence::class, ['numero_urgence:write']);
        $this->validateOrFail($numeroUrgence);

        $this->traceLogger->transactional(function () use ($numeroUrgence) {
            $this->entityManager->persist($numeroUrgence);
            $this->entityManager->flush();
            $this->traceLogger->log(sprintf('Création du numéro d\'urgence #%d', $numeroUrgence->getId()));
        });

        return $this->jsonResource($numeroUrgence, Response::HTTP_CREATED, ['numero_urgence:read']);
    }

    /**
     * Met à jour le numéro d'urgence d'id donné avec le corps JSON (mise à jour partielle : seuls les champs envoyés changent, PUT et PATCH sont équivalents).
     * Champs modifiables : libelle, numero.
     * ROLE_ADMIN requis (JWT). 200 avec la ressource modifiée ; 404 si introuvable ; 400 si JSON/types invalides ; 422 si validation échoue.
     * Enregistre une trace « Modification … » dans la même transaction.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $numeroUrgence = $this->findOrFail($this->repository, $id);
        $this->deserialize($request, NumeroUrgence::class, ['numero_urgence:write'], $numeroUrgence);
        $this->validateOrFail($numeroUrgence);

        $this->traceLogger->transactional(function () use ($numeroUrgence) {
            $this->entityManager->flush();
            $this->traceLogger->log(sprintf('Modification du numéro d\'urgence #%d', $numeroUrgence->getId()));
        });

        return $this->jsonResource($numeroUrgence, Response::HTTP_OK, ['numero_urgence:read']);
    }

    /**
     * Supprime le numéro d'urgence d'id donné.
     * ROLE_ADMIN requis (JWT). 204 sans contenu ; 404 si introuvable ; 409 si la ressource est encore référencée ailleurs.
     * Enregistre une trace « Suppression … » dans la même transaction.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $numeroUrgence = $this->findOrFail($this->repository, $id);

        $this->traceLogger->transactional(function () use ($id, $numeroUrgence) {
            $this->entityManager->remove($numeroUrgence);
            $this->entityManager->flush();
            $this->traceLogger->log(sprintf('Suppression du numéro d\'urgence #%d', $id));
        });

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
