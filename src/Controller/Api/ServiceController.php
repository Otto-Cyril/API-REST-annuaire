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

    /**
     * Liste des services (sans pagination).
     * Public (GET). Renvoie 200 avec le tableau complet.
     */
    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->jsonResource($this->repository->findAll(), Response::HTTP_OK, ['service:read']);
    }

    /**
     * Renvoie le service dont l'id est donné dans l'URL.
     * Public (GET). 200 si trouvé, 404 sinon.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return $this->jsonResource($this->findOrFail($this->repository, $id), Response::HTTP_OK, ['service:read']);
    }

    /**
     * Crée un service à partir du corps JSON.
     * Champs modifiables : libelle, localisation.
     * ROLE_ADMIN requis (JWT). 201 avec la ressource créée ; 400 si JSON/types invalides, 422 si validation échoue.
     * Enregistre une trace « Création … » dans la même transaction.
     */
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $service = $this->deserialize($request, Service::class, ['service:write']);
        $this->validateOrFail($service);

        $this->traceLogger->transactional(function () use ($service) {
            $this->entityManager->persist($service);
            $this->entityManager->flush();
            $this->traceLogger->log(sprintf('Création du service #%d', $service->getId()));
        });

        return $this->jsonResource($service, Response::HTTP_CREATED, ['service:read']);
    }

    /**
     * Met à jour le service d'id donné avec le corps JSON (mise à jour partielle : seuls les champs envoyés changent, PUT et PATCH sont équivalents).
     * Champs modifiables : libelle, localisation.
     * ROLE_ADMIN requis (JWT). 200 avec la ressource modifiée ; 404 si introuvable ; 400 si JSON/types invalides ; 422 si validation échoue.
     * Enregistre une trace « Modification … » dans la même transaction.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $service = $this->findOrFail($this->repository, $id);
        $this->deserialize($request, Service::class, ['service:write'], $service);
        $this->validateOrFail($service);

        $this->traceLogger->transactional(function () use ($service) {
            $this->entityManager->flush();
            $this->traceLogger->log(sprintf('Modification du service #%d', $service->getId()));
        });

        return $this->jsonResource($service, Response::HTTP_OK, ['service:read']);
    }

    /**
     * Supprime le service d'id donné. Échoue en 409 si le service est encore référencé par un personnel de garde.
     * ROLE_ADMIN requis (JWT). 204 sans contenu ; 404 si introuvable ; 409 si la ressource est encore référencée ailleurs.
     * Enregistre une trace « Suppression … » dans la même transaction.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $service = $this->findOrFail($this->repository, $id);

        $this->traceLogger->transactional(function () use ($id, $service) {
            $this->entityManager->remove($service);
            $this->entityManager->flush();
            $this->traceLogger->log(sprintf('Suppression du service #%d', $id));
        });

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
