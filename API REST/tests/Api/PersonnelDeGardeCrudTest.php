<?php

namespace App\Tests\Api;

class PersonnelDeGardeCrudTest extends ApiTestCase
{
    public function testAffichageAvecServiceMetierEtNumeros(): void
    {
        $service = $this->createService('Urgences', 'Bâtiment A');
        $metier = $this->createMetier('Médecin');
        $personnel = $this->createPersonnel('Dr Martin', $service, $metier);
        $this->createNumeroGarde($personnel, '0102030405', 'Mobile');

        $data = $this->request('GET', '/api/personnel/'.$personnel->getId());

        $this->assertStatus(200);
        $this->assertSame('Dr Martin', $data['libelle']);
        $this->assertSame('Urgences', $data['service']['libelle']);
        $this->assertSame('Médecin', $data['metier']['libelle']);
        $this->assertCount(1, $data['numerosGarde']);
        $this->assertSame('0102030405', $data['numerosGarde'][0]['numero']);
    }

    public function testCreationAvecRelations(): void
    {
        $service = $this->createService();
        $metier = $this->createMetier();

        $data = $this->request('POST', '/api/personnel', [
            'libelle' => 'Dr Durand',
            'serviceId' => $service->getId(),
            'metierId' => $metier->getId(),
        ], admin: true);

        $this->assertStatus(201);
        $this->assertSame($service->getId(), $data['service']['id']);
        $this->assertSame($metier->getId(), $data['metier']['id']);
        $traces = $this->request('GET', '/api/traces', admin: true);
        $this->assertSame('Création du personnel de garde #'.$data['id'], $traces[0]['actionRealise']);
    }

    public function testCreationSansRelationsRenvoie422(): void
    {
        $data = $this->request('POST', '/api/personnel', ['libelle' => 'Dr Durand'], admin: true);

        $this->assertStatus(422);
        $this->assertArrayHasKey('service', $data['errors']);
        $this->assertArrayHasKey('metier', $data['errors']);
    }

    public function testCreationAvecRelationsInvalidesRenvoie400(): void
    {
        $service = $this->createService();
        $metier = $this->createMetier();

        foreach ([999999, 'abc', null, true, [1], 0, -1, 2147483648] as $bad) {
            $this->request('POST', '/api/personnel', ['libelle' => 'X', 'serviceId' => $bad, 'metierId' => $metier->getId()], admin: true);
            $this->assertStatus(400);
        }
        $data = $this->request('POST', '/api/personnel', ['libelle' => 'X', 'serviceId' => $service->getId(), 'metierId' => 999999], admin: true);
        $this->assertStatus(400);
        $this->assertSame('metierId invalide.', $data['message']);
    }

    public function testModificationChangeLeService(): void
    {
        $personnel = $this->createPersonnel('Dr Martin');
        $autre = $this->createService('Pédiatrie');

        $data = $this->request('PATCH', '/api/personnel/'.$personnel->getId(), ['serviceId' => $autre->getId()], admin: true);

        $this->assertStatus(200);
        $this->assertSame('Pédiatrie', $data['service']['libelle']);
        $this->assertSame('Dr Martin', $data['libelle']);
    }

    public function testSuppressionSupprimeLesNumerosDeGarde(): void
    {
        $personnel = $this->createPersonnel('Dr Martin');
        $this->createNumeroGarde($personnel);
        $id = $personnel->getId();

        $this->request('DELETE', '/api/personnel/'.$id, admin: true);

        $this->assertStatus(204);
        $this->assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM numero_garde'));
    }

    public function testRecherchePaginee(): void
    {
        $urgences = $this->createService('Urgences', 'Bâtiment A');
        $pediatrie = $this->createService('Pédiatrie', 'Bâtiment B');
        $medecin = $this->createMetier('Médecin');
        $infirmier = $this->createMetier('Infirmier');
        $this->createPersonnel('Alpha', $urgences, $medecin);
        $this->createPersonnel('Bravo', $urgences, $infirmier);
        $this->createPersonnel('Charlie', $pediatrie, $medecin);

        // Pagination + en-têtes, tri par libellé
        $data = $this->request('GET', '/api/personnel?limit=2&page=1');
        $this->assertStatus(200);
        $this->assertSame(['Alpha', 'Bravo'], array_column($data, 'libelle'));
        $headers = $this->client->getResponse()->headers;
        $this->assertSame('3', $headers->get('X-Total-Count'));
        $this->assertSame('1', $headers->get('X-Page'));
        $this->assertSame('2', $headers->get('X-Per-Page'));
        $this->assertSame('2', $headers->get('X-Total-Pages'));

        $data = $this->request('GET', '/api/personnel?limit=2&page=2');
        $this->assertSame(['Charlie'], array_column($data, 'libelle'));

        // Recherche par mots (insensible à la casse) sur libellé, service, localisation, métier
        $this->assertSame(['Alpha'], array_column($this->request('GET', '/api/personnel?q=ALPHA'), 'libelle'));
        $this->assertSame(['Charlie'], array_column($this->request('GET', '/api/personnel?q=pédiatrie'), 'libelle'));
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], array_column($this->request('GET', '/api/personnel?q=bâtiment'), 'libelle'));
        $this->assertSame(['Charlie'], array_column($this->request('GET', '/api/personnel?q=bâtiment+pédiatrie'), 'libelle'));
        $this->assertSame(['Alpha'], array_column($this->request('GET', '/api/personnel?q=médecin+urgences'), 'libelle'));
        $this->assertSame([], $this->request('GET', '/api/personnel?q=zzz'));

        // Filtres
        $this->assertSame(['Alpha', 'Bravo'], array_column($this->request('GET', '/api/personnel?serviceId='.$urgences->getId()), 'libelle'));
        $this->assertSame(['Alpha', 'Charlie'], array_column($this->request('GET', '/api/personnel?metierId='.$medecin->getId()), 'libelle'));
        $this->assertSame(['Alpha'], array_column($this->request('GET', '/api/personnel?serviceId='.$urgences->getId().'&metierId='.$medecin->getId()), 'libelle'));
    }

    public function testRechercheEchappeLesJokersLike(): void
    {
        $this->createPersonnel('Alpha');

        $this->assertSame([], $this->request('GET', '/api/personnel?q=%25'));
        $this->assertSame([], $this->request('GET', '/api/personnel?q=_lpha'));
    }

    public function testParametresDeRechercheInvalides(): void
    {
        foreach (['limit=0', 'limit=101', 'limit=abc', 'page=0', 'page=9223372036854775807', 'serviceId=x', 'metierId=-1', 'q='.str_repeat('a', 51)] as $qs) {
            $this->request('GET', '/api/personnel?'.$qs);
            $this->assertStatus(400);
        }
    }
}
