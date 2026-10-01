<?php

namespace App\Controller\Api;

use App\Ldap\DirectoryCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/ad')]
class AdController extends AbstractController
{
    private const MIN_LENGTH = 2;
    private const MAX_LENGTH = 50;
    private const LIMIT = 20;

    public function __construct(private readonly DirectoryCatalog $catalog)
    {
    }

    /**
     * Recherche de comptes dans l'annuaire du personnel de l'AD, pour choisir la personne de garde à ajouter sans connaître son identifiant.
     * ROLE_ADMIN requis (JWT). Paramètre q : mots recherchés (identifiant, nom, e-mail, service, poste, numéro ; 2 à 50 caractères, 400 sinon).
     * Comptes actifs de l'unité d'organisation LDAP_DIRECTORY_DN, 20 résultats au plus, triés par nom ; 503 si l'AD est injoignable.
     * Renvoie [{ username, prenom, nom, email, libelle }].
     */
    #[Route('/recherche', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        if (mb_strlen($q) < self::MIN_LENGTH || mb_strlen($q) > self::MAX_LENGTH) {
            throw new BadRequestHttpException(sprintf('q doit contenir entre %d et %d caractères.', self::MIN_LENGTH, self::MAX_LENGTH));
        }

        $result = $this->catalog->search($q, null, null, DirectoryCatalog::SORT_NOM, 1, self::LIMIT);

        return new JsonResponse(array_map(static fn ($account) => [
            'username' => $account->username,
            'prenom' => $account->prenom,
            'nom' => $account->nom,
            'email' => $account->email,
            'libelle' => $account->fullName(),
        ], $result['items']));
    }
}
