<?php

namespace App\Tests\Api;

class ServiceCrudTest extends ApiTestCase
{
    public function testListe(): void
    {
        $this->createService('Urgences');
        $this->createService('Pédiatrie');

        $data = $this->request('GET', '/api/services');

        $this->assertStatus(200);
        $this->assertCount(2, $data);
        $this->assertSame(['id', 'libelle', 'localisation'], array_keys($data[0]));
    }

    public function testAffichage(): void
    {
        $service = $this->createService('Urgences', 'Bâtiment A');

        $data = $this->request('GET', '/api/services/'.$service->getId());

        $this->assertStatus(200);
        $this->assertSame('Urgences', $data['libelle']);
        $this->assertSame('Bâtiment A', $data['localisation']);
    }

    public function testAffichageIntrouvable(): void
    {
        $this->request('GET', '/api/services/999999');
        $this->assertStatus(404);
    }

    public function testIdNonNumeriqueEstUneRouteInconnue(): void
    {
        $this->request('GET', '/api/services/abc');
        $this->assertStatus(404);
    }

    public function testCreation(): void
    {
        $data = $this->request('POST', '/api/services', ['libelle' => 'Urgences', 'localisation' => 'Bâtiment A'], admin: true);

        $this->assertStatus(201);
        $this->assertSame('Urgences', $data['libelle']);
        $this->assertIsInt($data['id']);
        $this->request('GET', '/api/services/'.$data['id']);
        $this->assertStatus(200);
    }

    public function testCreationEnregistreUneTrace(): void
    {
        $data = $this->request('POST', '/api/services', ['libelle' => 'Urgences', 'localisation' => 'A'], admin: true);

        $traces = $this->request('GET', '/api/traces', admin: true);
        $this->assertCount(1, $traces);
        $this->assertSame('testeur', $traces[0]['username']);
        $this->assertSame('Création du service #'.$data['id'], $traces[0]['actionRealise']);
    }

    public function testCreationIgnoreLId(): void
    {
        $data = $this->request('POST', '/api/services', ['id' => 4242, 'libelle' => 'X', 'localisation' => 'Y'], admin: true);

        $this->assertStatus(201);
        $this->assertNotSame(4242, $data['id']);
    }

    public function testCreationChampsVidesRenvoie422(): void
    {
        $data = $this->request('POST', '/api/services', ['libelle' => ''], admin: true);

        $this->assertStatus(422);
        $this->assertArrayHasKey('libelle', $data['errors']);
        $this->assertArrayHasKey('localisation', $data['errors']);
        $this->assertIsArray($data['errors']['libelle']);
        $this->assertSame(0, $this->countTraces());
    }

    public function testCreationTropLongueRenvoie422(): void
    {
        $data = $this->request('POST', '/api/services', ['libelle' => str_repeat('a', 51), 'localisation' => 'Y'], admin: true);

        $this->assertStatus(422);
        $this->assertArrayHasKey('libelle', $data['errors']);
    }

    public function testCreationTypesInvalidesRenvoie400(): void
    {
        foreach ([['libelle' => 123, 'localisation' => 'y'], ['libelle' => ['a'], 'localisation' => 'y']] as $body) {
            $this->request('POST', '/api/services', $body, admin: true);
            $this->assertStatus(400);
        }
    }

    public function testCreationJsonInvalideNeRenvoiePasDe500(): void
    {
        foreach (['{pas du json', '', '123'] as $raw) {
            $this->request('POST', '/api/services', admin: true, rawBody: $raw);
            $this->assertContains($this->client->getResponse()->getStatusCode(), [400, 422], $raw);
        }
    }

    public function testModificationPartielle(): void
    {
        $service = $this->createService('Urgences', 'Bâtiment A');

        $data = $this->request('PATCH', '/api/services/'.$service->getId(), ['libelle' => 'Urgences adultes'], admin: true);

        $this->assertStatus(200);
        $this->assertSame('Urgences adultes', $data['libelle']);
        $this->assertSame('Bâtiment A', $data['localisation']);
    }

    public function testModificationPutEquivalentAPatch(): void
    {
        $service = $this->createService('Urgences', 'Bâtiment A');
        $id = $service->getId();

        $this->request('PUT', '/api/services/'.$id, ['localisation' => 'Bâtiment B'], admin: true);

        $this->assertStatus(200);
        $this->em->clear();
        $this->assertSame('Bâtiment B', $this->em->find($service::class, $id)->getLocalisation());
    }

    public function testModificationInvalideNeModifiePasEtNeTracePas(): void
    {
        $service = $this->createService('Urgences', 'A');
        $id = $service->getId();

        $this->request('PATCH', '/api/services/'.$id, ['libelle' => ''], admin: true);

        $this->assertStatus(422);
        $this->em->clear();
        $this->assertSame('Urgences', $this->em->find($service::class, $id)->getLibelle());
        $this->assertSame(0, $this->countTraces());
    }

    public function testModificationIntrouvable(): void
    {
        $this->request('PATCH', '/api/services/999999', ['libelle' => 'x'], admin: true);
        $this->assertStatus(404);
    }

    public function testIdHorsBornesRenvoie404(): void
    {
        $this->request('GET', '/api/services/99999999999');
        $this->assertStatus(404);
        $this->request('PATCH', '/api/services/2147483648', ['libelle' => 'x'], admin: true);
        $this->assertStatus(404);
    }

    public function testSuppression(): void
    {
        $id = $this->createService()->getId();

        $this->request('DELETE', '/api/services/'.$id, admin: true);

        $this->assertStatus(204);
        $this->request('GET', '/api/services/'.$id);
        $this->assertStatus(404);
        $traces = $this->request('GET', '/api/traces', admin: true);
        $this->assertSame('Suppression du service #'.$id, $traces[0]['actionRealise']);
    }

    public function testSuppressionIntrouvable(): void
    {
        $this->request('DELETE', '/api/services/999999', admin: true);
        $this->assertStatus(404);
    }

    public function testSuppressionRefuseeSiReferenceParUnPersonnel(): void
    {
        $service = $this->createService();
        $this->createPersonnel('Dr Martin', $service);

        $data = $this->request('DELETE', '/api/services/'.$service->getId(), admin: true);

        $this->assertStatus(409);
        $this->assertArrayHasKey('message', $data);
    }
}
