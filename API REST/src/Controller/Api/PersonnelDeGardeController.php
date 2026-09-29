<?php

namespace App\Controller\Api;

use App\Entity\PersonnelDeGarde;
use App\Repository\MetierRepository;
use App\Repository\PersonnelDeGardeRepository;
use App\Repository\ServiceRepository;
use App\Service\TraceLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/personnel')]
class PersonnelDeGardeController extends AbstractApiController
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly PersonnelDeGardeRepository $repository,
        private readonly ServiceRepository $serviceRepository,
        private readonly MetierRepository $metierRepository,
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
     * Recherche et liste paginée des personnels de garde (avec service, métier et numéros de garde).
     * Public (GET). Paramètres : q (recherche par mots sur libellé du personnel, service, localisation, métier ; 50 car. max),
     * serviceId et metierId (filtres), page (défaut 1), limit (défaut 20, max 100).
     * Renvoie 200 et les en-têtes X-Total-Count, X-Page, X-Per-Page, X-Total-Pages ; 400 si un paramètre est invalide.
     */
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        if (mb_strlen($q) > 50) {
            throw new BadRequestHttpException('q ne doit pas dépasser 50 caractères.');
        }

        [$page, $limit] = $this->pagination($request, self::DEFAULT_LIMIT, self::MAX_LIMIT);

        $result = $this->repository->search(
            '' === $q ? null : $q,
            $this->queryId($request, 'serviceId'),
            $this->queryId($request, 'metierId'),
            $page,
            $limit,
        );

        return $this->paginatedResponse($result, $page, $limit, ['personnel:read']);
    }

    /**
     * Renvoie le personnel de garde dont l'id est donné dans l'URL.
     * Public (GET). 200 si trouvé, 404 sinon.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return $this->jsonResource($this->findOrFail($this->repository, $id), Response::HTTP_OK, ['personnel:read']);
    }

    /**
     * Crée un personnel de garde à partir du corps JSON.
     * Champs modifiables : libelle ; serviceId et metierId (ids du service et du métier, obligatoires à la création, 400 si introuvables).
     * ROLE_ADMIN requis (JWT). 201 avec la ressource créée ; 400 si JSON/types invalides, 422 si validation échoue.
     * Enregistre une trace « Création … » dans la même transaction.
     */
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $personnel = $this->deserialize($request, PersonnelDeGarde::class, ['personnel:write']);
        $this->applyRelations($personnel, $request);
        $this->validateOrFail($personnel);

        $this->traceLogger->transactional(function () use ($personnel) {
            $this->entityManager->persist($personnel);
            $this->entityManager->flush();
            $this->traceLogger->log(sprintf('Création du personnel de garde #%d', $personnel->getId()));
        });

        return $this->jsonResource($personnel, Response::HTTP_CREATED, ['personnel:read']);
    }

    /**
     * Met à jour le personnel de garde d'id donné avec le corps JSON (mise à jour partielle : seuls les champs envoyés changent, PUT et PATCH sont équivalents).
     * Champs modifiables : libelle ; serviceId et metierId (ids du service et du métier, obligatoires à la création, 400 si introuvables).
     * ROLE_ADMIN requis (JWT). 200 avec la ressource modifiée ; 404 si introuvable ; 400 si JSON/types invalides ; 422 si validation échoue.
     * Enregistre une trace « Modification … » dans la même transaction.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $personnel = $this->findOrFail($this->repository, $id);
        $this->deserialize($request, PersonnelDeGarde::class, ['personnel:write'], $personnel);
        $this->applyRelations($personnel, $request);
        $this->validateOrFail($personnel);

        $this->traceLogger->transactional(function () use ($personnel) {
            $this->entityManager->flush();
            $this->traceLogger->log(sprintf('Modification du personnel de garde #%d', $personnel->getId()));
        });

        return $this->jsonResource($personnel, Response::HTTP_OK, ['personnel:read']);
    }

    /**
     * Supprime le personnel de garde d'id donné. Supprime aussi ses numéros de garde (cascade).
     * ROLE_ADMIN requis (JWT). 204 sans contenu ; 404 si introuvable ; 409 si la ressource est encore référencée ailleurs.
     * Enregistre une trace « Suppression … » dans la même transaction.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $personnel = $this->findOrFail($this->repository, $id);

        $this->traceLogger->transactional(function () use ($id, $personnel) {
            $this->entityManager->remove($personnel);
            $this->entityManager->flush();
            $this->traceLogger->log(sprintf('Suppression du personnel de garde #%d', $id));
        });

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function applyRelations(PersonnelDeGarde $personnel, Request $request): void
    {
        $data = $this->requestData($request);

        if (null !== $service = $this->findRelation($data, 'serviceId', $this->serviceRepository)) {
            $personnel->setService($service);
        }

        if (null !== $metier = $this->findRelation($data, 'metierId', $this->metierRepository)) {
            $personnel->setMetier($metier);
        }
    }
}
