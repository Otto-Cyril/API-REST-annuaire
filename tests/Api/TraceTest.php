<?php

namespace App\Tests\Api;

use App\Entity\Trace;

class TraceTest extends ApiTestCase
{
    private function creerTraces(int $nombre): void
    {
        for ($i = 1; $i <= $nombre; ++$i) {
            $trace = (new Trace())
                ->setUsername('testeur')
                ->setDateAction(new \DateTimeImmutable("2026-01-01 00:00:$i"))
                ->setActionRealise("Action $i");
            $this->em->persist($trace);
        }
        $this->em->flush();
    }

    public function testListePagineeDuPlusRecentAuPlusAncien(): void
    {
        $this->creerTraces(5);

        $data = $this->request('GET', '/api/traces?limit=2&page=1', admin: true);

        $this->assertStatus(200);
        $this->assertSame(['Action 5', 'Action 4'], array_column($data, 'actionRealise'));
        $headers = $this->client->getResponse()->headers;
        $this->assertSame('5', $headers->get('X-Total-Count'));
        $this->assertSame('3', $headers->get('X-Total-Pages'));

        $data = $this->request('GET', '/api/traces?limit=2&page=3', admin: true);
        $this->assertSame(['Action 1'], array_column($data, 'actionRealise'));
    }

    public function testLimiteParDefautEtMaximum(): void
    {
        $this->request('GET', '/api/traces', admin: true);
        $this->assertSame('50', $this->client->getResponse()->headers->get('X-Per-Page'));

        $this->request('GET', '/api/traces?limit=201', admin: true);
        $this->assertStatus(400);
    }

    public function testAffichageEtIntrouvable(): void
    {
        $this->creerTraces(1);
        $id = $this->request('GET', '/api/traces', admin: true)[0]['id'];

        $data = $this->request('GET', '/api/traces/'.$id, admin: true);
        $this->assertStatus(200);
        $this->assertSame(['id', 'username', 'dateAction', 'actionRealise'], array_keys($data));

        $this->request('GET', '/api/traces/999999', admin: true);
        $this->assertStatus(404);
    }

    public function testLesTracesNeSontPasModifiablesParLApi(): void
    {
        foreach (['POST', 'PUT', 'DELETE'] as $method) {
            $this->request($method, '/api/traces/1', ['actionRealise' => 'x'], admin: true);
            $this->assertContains($this->client->getResponse()->getStatusCode(), [404, 405]);
        }
    }
}
