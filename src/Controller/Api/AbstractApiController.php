<?php

namespace App\Controller\Api;

use Doctrine\Persistence\ObjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractApiController extends AbstractController
{
    abstract protected function getSerializer(): SerializerInterface;

    abstract protected function getValidator(): ValidatorInterface;

    protected function jsonResource(mixed $data, int $status, array $groups): JsonResponse
    {
        $json = $this->getSerializer()->serialize($data, 'json', ['groups' => $groups]);

        return new JsonResponse($json, $status, [], true);
    }

    protected function findOrFail(ObjectRepository $repository, int $id): object
    {
        $entity = $repository->find($id);
        if (null === $entity) {
            throw new NotFoundHttpException('Ressource introuvable.');
        }

        return $entity;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     * @param string[]        $groups
     *
     * @return T
     */
    protected function deserialize(Request $request, string $class, array $groups, ?object $existing = null): object
    {
        $context = ['groups' => $groups];
        if (null !== $existing) {
            $context['object_to_populate'] = $existing;
        }

        try {
            return $this->getSerializer()->deserialize($request->getContent(), $class, 'json', $context);
        } catch (NotEncodableValueException) {
            throw new BadRequestHttpException('Corps de requête JSON invalide.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestData(Request $request): array
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            throw new BadRequestHttpException('Corps de requête JSON invalide.');
        }

        return $data;
    }

    /**
     * Résout une relation à partir d'un identifiant reçu en entrée (ex. serviceId).
     * Retourne null si la clé est absente ; 400 si la valeur n'est pas un identifiant valide ou introuvable.
     *
     * @param array<string, mixed> $data
     */
    protected function findRelation(array $data, string $key, ObjectRepository $repository): ?object
    {
        if (!\array_key_exists($key, $data)) {
            return null;
        }

        $id = \is_bool($data[$key]) ? false : filter_var($data[$key], \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
        $entity = false === $id ? null : $repository->find($id);
        if (null === $entity) {
            throw new BadRequestHttpException(sprintf('%s invalide.', $key));
        }

        return $entity;
    }

    protected function validateOrFail(object $entity): void
    {
        $violations = $this->getValidator()->validate($entity);
        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            throw new BadRequestHttpException(json_encode($errors, \JSON_UNESCAPED_UNICODE));
        }
    }
}
