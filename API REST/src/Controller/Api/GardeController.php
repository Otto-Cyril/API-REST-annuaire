<?php

namespace App\Controller\Api;

use App\Entity\Garde;
use App\Repository\GardeRepository;
use App\Repository\PersonnelDeGardeRepository;
use App\Service\TraceLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/gardes')]
class GardeController extends AbstractApiController
{
    private const READ = ['garde:read', 'personnel:read'];

    public function __construct(
        private readonly GardeRepository $repository,
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
     * Liste des gardes. Public (GET).
     * Avec le paramètre date (AAAA-MM-JJ) : uniquement les gardes couvrant ce jour (bornes incluses), triées par nom —
     * c'est la « garde en cours » ; le client envoie la date du jour.
     * Sans date : toutes les gardes, les plus récentes d'abord (planning d'administration).
     * 400 si date n'est pas une date valide.
     */
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $date = $request->query->get('date');
        if (null === $date || '' === $date) {
            return $this->jsonResource($this->repository->findAllOrdered(), Response::HTTP_OK, self::READ);
        }

        return $this->jsonResource($this->repository->findActiveOn($this->parseDate($date)), Response::HTTP_OK, self::READ);
    }

    /**
     * Prochaine garde à venir après le jour donné : les gardes qui commencent le premier jour postérieur à date.
     * Public (GET). date (AAAA-MM-JJ) obligatoire, 400 sinon. Liste vide s'il n'y a aucune garde à venir.
     */
    #[Route('/prochaines', methods: ['GET'])]
    public function prochaines(Request $request): JsonResponse
    {
        $date = $request->query->get('date');
        if (null === $date || '' === $date) {
            throw new BadRequestHttpException('date est obligatoire (format attendu : AAAA-MM-JJ).');
        }

        return $this->jsonResource($this->repository->findNextStartingAfter($this->parseDate($date)), Response::HTTP_OK, self::READ);
    }

    /**
     * Renvoie la garde dont l'id est donné dans l'URL. Public (GET). 200 si trouvée, 404 sinon.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return $this->jsonResource($this->findOrFail($this->repository, $id), Response::HTTP_OK, self::READ);
    }

    /**
     * Crée une garde. Champs : dateDebut et dateFin (AAAA-MM-JJ, fin >= début) ; personnelDeGardeId (obligatoire, 400 si introuvable).
     * ROLE_ADMIN requis (JWT). 201 avec la ressource créée ; 400 si JSON/types invalides, 422 si validation échoue.
     * Enregistre une trace dans la même transaction.
     */
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $garde = $this->deserialize($request, Garde::class, ['garde:write']);
        $this->applyRelations($garde, $request);
        $this->validateOrFail($garde);

        $this->traceLogger->transactional(function () use ($garde) {
            $this->entityManager->persist($garde);
            $this->entityManager->flush();
            $this->traceLogger->log(sprintf('Création de la garde #%d', $garde->getId()));
        });

        return $this->jsonResource($garde, Response::HTTP_CREATED, self::READ);
    }

    /**
     * Modifie une garde (PUT ou PATCH, champs partiels acceptés). ROLE_ADMIN requis. 200, 404, 400 ou 422.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $garde = $this->findOrFail($this->repository, $id);
        $this->deserialize($request, Garde::class, ['garde:write'], $garde);
        $this->applyRelations($garde, $request);
        $this->validateOrFail($garde);

        $this->traceLogger->transactional(function () use ($garde) {
            $this->entityManager->flush();
            $this->traceLogger->log(sprintf('Modification de la garde #%d', $garde->getId()));
        });

        return $this->jsonResource($garde, Response::HTTP_OK, self::READ);
    }

    /**
     * Supprime une garde. ROLE_ADMIN requis. 204 ou 404.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $garde = $this->findOrFail($this->repository, $id);

        $this->traceLogger->transactional(function () use ($id, $garde) {
            $this->entityManager->remove($garde);
            $this->entityManager->flush();
            $this->traceLogger->log(sprintf('Suppression de la garde #%d', $id));
        });

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function parseDate(mixed $date): \DateTimeImmutable
    {
        $jour = \is_string($date) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        if (false === $jour || $jour->format('Y-m-d') !== $date) {
            throw new BadRequestHttpException('date invalide (format attendu : AAAA-MM-JJ).');
        }

        return $jour;
    }

    private function applyRelations(Garde $garde, Request $request): void
    {
        $data = $this->requestData($request);
        if (null !== $personnel = $this->findRelation($data, 'personnelDeGardeId', $this->personnelRepository)) {
            $garde->setPersonnelDeGarde($personnel);
        }
    }
}
