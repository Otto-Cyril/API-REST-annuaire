<?php

namespace App\Controller\Api;

use App\Repository\TraceRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/traces')]
class TraceController extends AbstractApiController
{
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 200;
    private const ACTIONS = ['Création', 'Modification', 'Suppression'];

    public function __construct(
        private readonly TraceRepository $repository,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
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
     * Liste paginée du journal d'audit, les plus récentes d'abord.
     * ROLE_ADMIN requis. Paramètres : page (défaut 1), limit (défaut 50, max 200) ; en-têtes X-Total-Count, X-Page, X-Per-Page, X-Total-Pages.
     * Filtres facultatifs : username (contient, insensible à la casse), action (Création, Modification ou Suppression),
     * from et to (AAAA-MM-JJ, bornes incluses). 400 si une valeur est invalide.
     */
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        [$page, $limit] = $this->pagination($request, self::DEFAULT_LIMIT, self::MAX_LIMIT);

        $username = trim((string) $request->query->get('username', ''));
        if (mb_strlen($username) > 50) {
            throw new BadRequestHttpException('username invalide (50 caractères max).');
        }
        $action = (string) $request->query->get('action', '');
        if ('' !== $action && !\in_array($action, self::ACTIONS, true)) {
            throw new BadRequestHttpException(sprintf('action invalide (%s).', implode(', ', self::ACTIONS)));
        }

        $result = $this->repository->paginate(
            $page,
            $limit,
            '' === $username ? null : $username,
            '' === $action ? null : $action,
            $this->queryDate($request, 'from'),
            $this->queryDate($request, 'to'),
        );

        return $this->paginatedResponse($result, $page, $limit, ['trace:read']);
    }

    private function queryDate(Request $request, string $name): ?\DateTimeImmutable
    {
        $value = $request->query->get($name);
        if (null === $value || '' === $value) {
            return null;
        }
        $jour = \is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if (false === $jour || $jour->format('Y-m-d') !== $value) {
            throw new BadRequestHttpException(sprintf('%s invalide (format attendu : AAAA-MM-JJ).', $name));
        }

        return $jour;
    }

    /**
     * Renvoie une trace d'audit par id. ROLE_ADMIN requis ; 404 si introuvable.
     */
    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return $this->jsonResource($this->findOrFail($this->repository, $id), Response::HTTP_OK, ['trace:read']);
    }
}
