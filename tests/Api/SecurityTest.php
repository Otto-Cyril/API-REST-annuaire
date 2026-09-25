<?php

namespace App\Tests\Api;

use PHPUnit\Framework\Attributes\DataProvider;

class SecurityTest extends ApiTestCase
{
    /** @return iterable<string, array{string}> */
    public static function publicReadRoutes(): iterable
    {
        foreach (['services', 'metiers', 'personnel', 'numeros-garde', 'numeros-urgence'] as $r) {
            yield $r => ["/api/$r"];
        }
    }

    #[DataProvider('publicReadRoutes')]
    public function testLectureEstPubliqueSansToken(string $uri): void
    {
        $this->request('GET', $uri);
        $this->assertStatus(200);
    }

    /** @return iterable<string, array{string, string}> */
    public static function writeRoutes(): iterable
    {
        foreach (['services', 'metiers', 'personnel', 'numeros-garde', 'numeros-urgence'] as $r) {
            yield "POST $r" => ['POST', "/api/$r"];
            yield "PUT $r" => ['PUT', "/api/$r/1"];
            yield "PATCH $r" => ['PATCH', "/api/$r/1"];
            yield "DELETE $r" => ['DELETE', "/api/$r/1"];
        }
    }

    #[DataProvider('writeRoutes')]
    public function testEcritureRefuseeSansToken(string $method, string $uri): void
    {
        $this->request($method, $uri, ['libelle' => 'x']);
        $this->assertStatus(401);
    }

    public function testTracesRefuseesSansToken(): void
    {
        $this->request('GET', '/api/traces');
        $this->assertStatus(401);
        $this->request('GET', '/api/traces/1');
        $this->assertStatus(401);
    }

    public function testTokenInvalideRefuse(): void
    {
        $this->client->request('POST', '/api/services', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer pas.un.jwt',
        ], '{"libelle":"x","localisation":"y"}');
        $this->assertStatus(401);
    }

    public function testTracesAccessiblesAvecTokenAdmin(): void
    {
        $this->request('GET', '/api/traces', admin: true);
        $this->assertStatus(200);
    }

    public function testRouteInconnueRenvoieUnJson404(): void
    {
        $data = $this->request('GET', '/api/inexistant');
        $this->assertStatus(404);
        $this->assertArrayHasKey('message', $data);
    }

    public function testLoginSansCorpsValideRenvoie401(): void
    {
        foreach ([null, '{}', '"abc"', '{"username":"x"}', '{"username":"","password":""}', '{"username":1,"password":2}'] as $raw) {
            $data = $this->request('POST', '/api/login', rawBody: $raw);
            $this->assertStatus(401);
            $this->assertSame('Identifiant et mot de passe requis.', $data['message']);
        }
    }
}
