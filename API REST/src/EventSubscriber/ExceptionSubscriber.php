<?php

namespace App\EventSubscriber;

use App\Exception\ValidationFailedException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof ForeignKeyConstraintViolationException) {
            $event->setResponse(new JsonResponse([
                'message' => 'Opération impossible : cette ressource est encore référencée par d\'autres données.',
            ], Response::HTTP_CONFLICT));

            return;
        }

        if ($exception instanceof ValidationFailedException) {
            $event->setResponse(new JsonResponse([
                'message' => $exception->getMessage(),
                'errors' => $exception->getErrors(),
            ], $exception->getStatusCode()));

            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $message = $exception->getMessage();
        } else {
            // Ne jamais exposer les détails internes (SQL, chemins...) hors mode debug.
            $status = Response::HTTP_INTERNAL_SERVER_ERROR;
            $message = $this->debug ? $exception->getMessage() : 'Erreur interne du serveur.';
        }

        $event->setResponse(new JsonResponse(['message' => $message], $status));
    }
}
