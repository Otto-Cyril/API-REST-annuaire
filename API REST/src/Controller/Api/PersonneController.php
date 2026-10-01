<?php

namespace App\Controller\Api;

use App\Ldap\DirectoryCatalog;
use App\Ldap\DirectoryEntry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Annuaire du personnel : lecture seule, directement depuis l'AD (voir DirectoryCatalog). Aucune écriture : on modifie l'AD.
 */
#[Route('/api/personnes')]
class PersonneController extends AbstractController
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    public function __construct(private readonly DirectoryCatalog $catalog)
    {
    }

    /**
     * Recherche et liste paginée du personnel (comptes actifs de l'unité d'organisation LDAP_DIRECTORY_DN de l'AD). Public (GET).
     * Paramètres : q (mots recherchés dans le nom, l'e-mail, le service, le poste, un numéro ; 50 car. max), serviceId et metierId
     * (valeurs exactes du service et du poste, voir /services et /metiers), sort (nom, défaut, ou service), page (défaut 1), limit (défaut 20, max 100).
     * Renvoie 200 et les en-têtes X-Total-Count, X-Page, X-Per-Page, X-Total-Pages ; 400 si un paramètre est invalide ; 503 si l'AD est injoignable.
     */
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        if (mb_strlen($q) > 50) {
            throw new BadRequestHttpException('q ne doit pas dépasser 50 caractères.');
        }

        $sort = (string) $request->query->get('sort', DirectoryCatalog::SORT_NOM);
        if (!\in_array($sort, DirectoryCatalog::SORTS, true)) {
            throw new BadRequestHttpException(sprintf('sort invalide (valeurs : %s).', implode(', ', DirectoryCatalog::SORTS)));
        }

        $page = $this->positiveInt($request, 'page', 1);
        $limit = $this->positiveInt($request, 'limit', self::DEFAULT_LIMIT);
        if ($limit > self::MAX_LIMIT) {
            throw new BadRequestHttpException(sprintf('limit ne doit pas dépasser %d.', self::MAX_LIMIT));
        }

        $result = $this->catalog->search(
            '' === $q ? null : $q,
            $this->label($request, 'serviceId'),
            $this->label($request, 'metierId'),
            $sort,
            $page,
            $limit,
        );

        $response = new JsonResponse(array_map($this->present(...), $result['items']));
        $response->headers->add([
            'X-Total-Count' => $result['total'],
            'X-Page' => $page,
            'X-Per-Page' => $limit,
            'X-Total-Pages' => max(1, (int) ceil($result['total'] / $limit)),
        ]);

        return $response;
    }

    /**
     * Services (attribut department de l'AD) distincts, pour le filtre. Public (GET). Renvoie [{ id, libelle }] où id = libelle.
     */
    #[Route('/services', methods: ['GET'])]
    public function services(): JsonResponse
    {
        return new JsonResponse(array_map(static fn (string $label) => ['id' => $label, 'libelle' => $label], $this->catalog->services()));
    }

    /**
     * Postes (attribut title de l'AD) distincts, pour le filtre. Public (GET). Renvoie [{ id, libelle }] où id = libelle.
     */
    #[Route('/metiers', methods: ['GET'])]
    public function metiers(): JsonResponse
    {
        return new JsonResponse(array_map(static fn (string $label) => ['id' => $label, 'libelle' => $label], $this->catalog->metiers()));
    }

    /**
     * Répartition du personnel pour le tableau de bord. Public (GET).
     * Renvoie { total, services: [{ libelle, total }], metiers: [{ libelle, total }] }, du plus fréquent au moins fréquent.
     */
    #[Route('/stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        return new JsonResponse($this->catalog->statistics());
    }

    /**
     * Fiche d'une personne, par son identifiant AD. Public (GET). 200 si trouvée, 404 sinon.
     */
    #[Route('/{username}', requirements: ['username' => '[A-Za-z0-9._$-]{1,50}'], methods: ['GET'])]
    public function show(string $username): JsonResponse
    {
        $entry = $this->catalog->find($username) ?? throw new NotFoundHttpException('Ressource introuvable.');

        return new JsonResponse($this->present($entry));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DirectoryEntry $entry): array
    {
        return [
            'username' => $entry->username,
            'prenom' => $entry->prenom,
            'nom' => $entry->nom,
            'email' => $entry->email,
            'service' => null === $entry->department ? null : ['id' => $entry->department, 'libelle' => $entry->department],
            'metier' => null === $entry->title ? null : ['id' => $entry->title, 'libelle' => $entry->title],
            'numeros' => $entry->numbers,
        ];
    }

    private function positiveInt(Request $request, string $name, int $default): int
    {
        $value = $request->query->get($name);
        if (null === $value || '' === $value) {
            return $default;
        }

        $int = filter_var($value, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
        if (false === $int) {
            throw new BadRequestHttpException(sprintf('%s invalide.', $name));
        }

        return $int;
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
