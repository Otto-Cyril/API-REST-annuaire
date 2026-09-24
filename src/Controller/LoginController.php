<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LoginController
{
    /**
     * La requête est traitée par App\Security\LdapAuthenticator (firewall "login") ;
     * cette route n'existe que pour que le routeur la reconnaisse.
     */
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        return new JsonResponse(['message' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
    }
}
