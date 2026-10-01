<?php

namespace App\Controller\Api;

use App\Entity\PersonnelDeGarde;
use App\Ldap\DirectoryLookupInterface;
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

#[Route('/api/personnel')]
class PersonnelDeGardeController extends AbstractApiController
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly PersonnelDeGardeRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly TraceLogger $traceLogger,
        private readonly DirectoryLookupInterface $directory,
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
     * Le service et le métier sont les libellés de l'AD (department et title) copiés à l'enregistrement.
     * Public (GET). Paramètres : q (recherche par mots sur identifiant, libellé, service, métier ; 50 car. max),
     * serviceId et metierId (filtres : valeurs exactes du service et du métier, voir /services et /metiers), sort (nom, défaut, ou service), page (défaut 1), limit (défaut 20, max 100).
     * Renvoie 200 et les en-têtes X-Total-Count, X-Page, X-Per-Page, X-Total-Pages ; 400 si un paramètre est invalide.
     */
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        if (mb_strlen($q) > 50) {
            throw new BadRequestHttpException('q ne doit pas dépasser 50 caractères.');
        }

        $sort = (string) $request->query->get('sort', PersonnelDeGardeRepository::SORT_NOM);
        if (!\in_array($sort, PersonnelDeGardeRepository::SORTS, true)) {
            throw new BadRequestHttpException(sprintf('sort invalide (valeurs : %s).', implode(', ', PersonnelDeGardeRepository::SORTS)));
        }

        [$page, $limit] = $this->pagination($request, self::DEFAULT_LIMIT, self::MAX_LIMIT);

        $result = $this->repository->search(
            '' === $q ? null : $q,
            $this->label($request, 'serviceId'),
            $this->label($request, 'metierId'),
            $page,
            $limit,
            $sort,
        );

        return $this->paginatedResponse($result, $page, $limit, ['personnel:read']);
    }

    /**
     * Services du personnel de garde enregistré (distincts, triés), pour le filtre. Public (GET). Renvoie [{ id, libelle }] où id = libelle.
     */
    #[Route('/services', methods: ['GET'])]
    public function services(): JsonResponse
    {
        return new JsonResponse(array_map(static fn (string $label) => ['id' => $label, 'libelle' => $label], $this->repository->distinct('service')));
    }

    /**
     * Métiers du personnel de garde enregistré (distincts, triés), pour le filtre. Public (GET). Renvoie [{ id, libelle }] où id = libelle.
     */
    #[Route('/metiers', methods: ['GET'])]
    public function metiers(): JsonResponse
    {
        return new JsonResponse(array_map(static fn (string $label) => ['id' => $label, 'libelle' => $label], $this->repository->distinct('metier')));
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
     * Champ modifiable : username (identifiant AD, obligatoire : le libellé « Prénom Nom », le service et le métier sont lus dans l'AD,
     * 422 si introuvable ou déjà enregistré, 503 si l'AD est injoignable).
     * ROLE_ADMIN requis (JWT). 201 avec la ressource créée ; 400 si JSON/types invalides, 422 si validation échoue.
     * Enregistre une trace « Création … » dans la même transaction.
     */
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $personnel = $this->deserialize($request, PersonnelDeGarde::class, ['personnel:write']);
        $this->applyDirectory($personnel);
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
     * Champ modifiable : username (le libellé, le service et le métier sont relus dans l'AD s'il change).
     * ROLE_ADMIN requis (JWT). 200 avec la ressource modifiée ; 404 si introuvable ; 400 si JSON/types invalides ; 422 si validation échoue.
     * Enregistre une trace « Modification … » dans la même transaction.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $personnel = $this->findOrFail($this->repository, $id);
        $previous = $personnel->getUsername();
        $this->deserialize($request, PersonnelDeGarde::class, ['personnel:write'], $personnel);
        if (0 === strcasecmp((string) $previous, (string) $personnel->getUsername())) {
            $personnel->setUsername($previous); // inchangé : pas de nouvel appel à l'AD
        } else {
            $this->applyDirectory($personnel);
        }
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

    /**
     * Recopie depuis l'AD l'identifiant canonique, le libellé « Prénom Nom » (50 caractères au plus), le service (department) et le métier (title).
     */
    private function applyDirectory(PersonnelDeGarde $personnel): void
    {
        $account = $this->directoryAccount($this->directory, $personnel->getUsername());
        $personnel
            ->setUsername($account->username)
            ->setLibelle(mb_substr($account->fullName(), 0, 50))
            ->setService($account->department ? mb_substr($account->department, 0, 150) : null)
            ->setMetier($account->title ? mb_substr($account->title, 0, 150) : null);
    }

    private function label(Request $request, string $name): ?string
    {
        $value = $request->query->get($name);
        if (null === $value || '' === $value) {
            return null;
        }
        if (!\is_string($value) || mb_strlen($value) > 200) {
            throw new BadRequestHttpException(sprintf('%s invalide.', $name));
        }

        return $value;
    }
}
