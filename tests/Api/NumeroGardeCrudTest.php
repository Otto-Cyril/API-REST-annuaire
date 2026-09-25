<?php

namespace App\Tests\Api;

class NumeroGardeCrudTest extends ApiTestCase
{
    public function testListeEtAffichage(): void
    {
        $personnel = $this->createPersonnel('Dr Martin');
        $numero = $this->createNumeroGarde($personnel, '0102030405', 'Mobile');

        $this->assertCount(1, $this->request('GET', '/api/numeros-garde'));
        $data = $this->request('GET', '/api/numeros-garde/'.$numero->getId());

        $this->assertStatus(200);
        $this->assertSame('0102030405', $data['numero']);
        $this->assertSame('Mobile', $data['type']);
        $this->assertSame($personnel->getId(), $data['personnelDeGarde']['id']);
    }

    public function testCreationEtTrace(): void
    {
        $personnel = $this->createPersonnel('Dr Martin');

        $data = $this->request('POST', '/api/numeros-garde', [
            'numero' => '0607080910',
            'type' => 'Astreinte',
            'personnelDeGardeId' => $personnel->getId(),
        ], admin: true);

        $this->assertStatus(201);
        $this->assertSame($personnel->getId(), $data['personnelDeGarde']['id']);
        $traces = $this->request('GET', '/api/traces', admin: true);
        $this->assertSame('Création du numéro de garde #'.$data['id'], $traces[0]['actionRealise']);
    }

    public function testCreationSansPersonnelRenvoie422(): void
    {
        $data = $this->request('POST', '/api/numeros-garde', ['numero' => '1', 'type' => 'x'], admin: true);

        $this->assertStatus(422);
        $this->assertArrayHasKey('personnelDeGarde', $data['errors']);
    }

    public function testCreationAvecPersonnelInvalideRenvoie400(): void
    {
        foreach ([999999, 'abc', null, false] as $bad) {
            $data = $this->request('POST', '/api/numeros-garde', ['numero' => '1', 'type' => 'x', 'personnelDeGardeId' => $bad], admin: true);
            $this->assertStatus(400);
            $this->assertSame('personnelDeGardeId invalide.', $data['message']);
        }
    }

    public function testModificationEtRattachementAUnAutrePersonnel(): void
    {
        $a = $this->createPersonnel('Dr A');
        $b = $this->createPersonnel('Dr B');
        $numero = $this->createNumeroGarde($a);

        $data = $this->request('PATCH', '/api/numeros-garde/'.$numero->getId(), ['numero' => '0999999999', 'personnelDeGardeId' => $b->getId()], admin: true);

        $this->assertStatus(200);
        $this->assertSame('0999999999', $data['numero']);
        $this->assertSame($b->getId(), $data['personnelDeGarde']['id']);
    }

    public function testSuppression(): void
    {
        $id = $this->createNumeroGarde($this->createPersonnel('Dr Martin'))->getId();

        $this->request('DELETE', '/api/numeros-garde/'.$id, admin: true);

        $this->assertStatus(204);
        $this->request('GET', '/api/numeros-garde/'.$id);
        $this->assertStatus(404);
    }
}
