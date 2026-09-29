<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ValidationFailedException extends HttpException
{
    /**
     * @param array<string, string[]> $errors messages de validation groupés par champ
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(Response::HTTP_UNPROCESSABLE_ENTITY, 'Données invalides.');
    }

    /**
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
