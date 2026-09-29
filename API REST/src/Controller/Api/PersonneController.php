<?php

namespace App\Controller\Api;

use App\Entity\Personne;
use App\Repository\MetierRepository;
use App\Repository\PersonneRepository;
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

#[Route('/api/personnes')]
class PersonneController extends AbstractApiController
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    // « personnel:read » : sérialise le service et le métier imbriqués comme pour le personnel de garde.
    private const READ_GROUPS = ['personne:read', 'personnel:read'];

    public function __construct(
        private readonly PersonneRepository $repository,
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
     * Recherche et liste paginée de l'annuaire du personnel (avec service et métier).
     * Public (GET). Paramètres : q (recherche par mots sur nom, prénom, e-mail, téléphone, service, localisation, métier ; 50 car. max),
     * serviceId et metierId (filtres), sort (nom, défaut, ou service), page (défaut 1), limit (défaut 20, max 100).
     * Renvoie 200 et les en-têtes X-Total-Count, X-Page, X-Per-Page, X-Total-Pages ; 400 si un paramètre est invalide.
     */
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        if (mb_strlen($q) > 50) {
            throw new BadRequestHttpException('q ne doit pas dépasser 50 caractères.');
        }

        $sort = (string) $request->query->get('sort', PersonneRepository::SORT_NOM);
        if (!\in_array($sort, PersonneRepository::SORTS, true)) {
            throw new BadRequestHttpException(sprintf('sort invalide (valeurs : %s).', implode(', ', PersonneRepository::SORTS)));
        }

        [$page, $limit] = $this->pagination($request, self::DEFAULT_LIMIT, self::MAX_LIMIT);

        $result = $this->repository->search(
            '' === $q ? null : $q,
            $this->queryId($request, 'serviceId'),
            $this->queryId($request, 'metierId'),
            $page,
            $limit,
            $sort,
        );

        return $this->paginatedResponse($result, $page, $limit, self::READ_GROUPS);
    }

    /**
     * Renvoie la personne dont l'id est donné dans l'URL.
     * Public (GET). 200 si trouvée, 404 sinon.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return $this->jsonResource($this->findOrFail($this->repository, $id), Response::HTTP_OK, self::READ_GROUPS);
    }

    /**
     * Crée une personne à partir du corps JSON.
     * Champs modifiables : nom, prenom, email (optionnel), telephone (optionnel) ; serviceId et metierId (ids du service et du métier, obligatoires à la création, 400 si introuvables).
     * ROLE_ADMIN requis (JWT). 201 avec la ressource créée ; 400 si JSON/types invalides, 422 si validation échoue.
     * Enregistre une trace « Création … » dans la même transaction.
     */
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $personne = $this->deserialize($request, Personne::class, ['personne:write']);
        $this->applyRelations($personne, $request);
        $this->validateOrFail($personne);

        $this->traceLogger->transactional(function () use ($personne) {
            $this->entityManager->persist($personne);
            $this->entityManager->flush();
            $this->traceLogger->log(sprintf('Création de la personne #%d', $personne->getId()));
        });

        return $this->jsonResource($personne, Response::HTTP_CREATED, self::READ_GROUPS);
    }

    /**
     * Met à jour la personne d'id donné avec le corps JSON (mise à jour partielle : seuls les champs envoyés changent, PUT et PATCH sont équivalents).
     * Champs modifiables : nom, prenom, email, telephone ; serviceId et metierId.
     * ROLE_ADMIN requis (JWT). 200 avec la ressource modifiée ; 404 si introuvable ; 400 si JSON/types invalides ; 422 si validation échoue.
     * Enregistre une trace « Modification … » dans la même transaction.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $personne = $this->findOrFail($this->repository, $id);
        $this->deserialize($request, Personne::class, ['personne:write'], $personne);
        $this->applyRelations($personne, $request);
        $this->validateOrFail($personne);

        $this->traceLogger->transactional(function () use ($personne) {
            $this->entityManager->flush();
            $this->traceLogger->log(sprintf('Modification de la personne #%d', $personne->getId()));
        });

        return $this->jsonResource($personne, Response::HTTP_OK, self::READ_GROUPS);
    }

    /**
     * Supprime la personne d'id donné.
     * ROLE_ADMIN requis (JWT). 204 sans contenu ; 404 si introuvable.
     * Enregistre une trace « Suppression … » dans la même transaction.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $personne = $this->findOrFail($this->repository, $id);

        $this->traceLogger->transactional(function () use ($id, $personne) {
            $this->entityManager->remove($personne);
            $this->entityManager->flush();
            $this->traceLogger->log(sprintf('Suppression de la personne #%d', $id));
        });

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function applyRelations(Personne $personne, Request $request): void
    {
        $data = $this->requestData($request);

        if (null !== $service = $this->findRelation($data, 'serviceId', $this->serviceRepository)) {
            $personne->setService($service);
        }

        if (null !== $metier = $this->findRelation($data, 'metierId', $this->metierRepository)) {
            $personne->setMetier($metier);
        }
    }
}
