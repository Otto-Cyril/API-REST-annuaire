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

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        if (mb_strlen($q) > 50) {
            throw new BadRequestHttpException('q ne doit pas dépasser 50 caractères.');
        }

        $page = $this->queryId($request, 'page') ?? 1;
        $limit = $this->queryId($request, 'limit') ?? self::DEFAULT_LIMIT;
        if ($limit > self::MAX_LIMIT) {
            throw new BadRequestHttpException(sprintf('limit ne doit pas dépasser %d.', self::MAX_LIMIT));
        }

        $result = $this->repository->search(
            '' === $q ? null : $q,
            $this->queryId($request, 'serviceId'),
            $this->queryId($request, 'metierId'),
            $page,
            $limit,
        );

        $response = $this->jsonResource(iterator_to_array($result), Response::HTTP_OK, ['personnel:read']);
        $total = \count($result);
        $response->headers->add([
            'X-Total-Count' => $total,
            'X-Page' => $page,
            'X-Per-Page' => $limit,
            'X-Total-Pages' => max(1, (int) ceil($total / $limit)),
        ]);

        return $response;
    }

    private function queryId(Request $request, string $name): ?int
    {
        $value = $request->query->get($name);
        if (null === $value || '' === $value) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $id) {
            throw new BadRequestHttpException(sprintf('%s invalide.', $name));
        }

        return $id;
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return $this->jsonResource($this->findOrFail($this->repository, $id), Response::HTTP_OK, ['personnel:read']);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $personnel = $this->deserialize($request, PersonnelDeGarde::class, ['personnel:write']);
        $this->applyRelations($personnel, $request);
        $this->validateOrFail($personnel);

        $this->entityManager->persist($personnel);
        $this->entityManager->flush();
        $this->traceLogger->log(sprintf('Création du personnel de garde #%d', $personnel->getId()));

        return $this->jsonResource($personnel, Response::HTTP_CREATED, ['personnel:read']);
    }

    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $personnel = $this->findOrFail($this->repository, $id);
        $this->deserialize($request, PersonnelDeGarde::class, ['personnel:write'], $personnel);
        $this->applyRelations($personnel, $request);
        $this->validateOrFail($personnel);

        $this->entityManager->flush();
        $this->traceLogger->log(sprintf('Modification du personnel de garde #%d', $personnel->getId()));

        return $this->jsonResource($personnel, Response::HTTP_OK, ['personnel:read']);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $personnel = $this->findOrFail($this->repository, $id);

        $this->entityManager->remove($personnel);
        $this->entityManager->flush();
        $this->traceLogger->log(sprintf('Suppression du personnel de garde #%d', $id));

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
