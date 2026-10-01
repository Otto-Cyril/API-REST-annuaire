<?php

namespace App\Tests\Api;

use App\Tests\Support\FakeDirectoryLookup;

class PersonnelDeGardeCrudTest extends ApiTestCase
{
    public function testAffichageAvecServiceMetierEtNumeros(): void
    {
        $personnel = $this->createPersonnel('Dr Martin', 'Urgences', 'Médecin');
        $this->createNumeroGarde($personnel, '0102030405', 'Mobile');

        $data = $this->request('GET', '/api/personnel/'.$personnel->getId());

        $this->assertStatus(200);
        $this->assertSame('Dr Martin', $data['libelle']);
        $this->assertSame(['id' => 'Urgences', 'libelle' => 'Urgences'], $data['service']);
        $this->assertSame(['id' => 'Médecin', 'libelle' => 'Médecin'], $data['metier']);
        $this->assertCount(1, $data['numerosGarde']);
        $this->assertSame('0102030405', $data['numerosGarde'][0]['numero']);
    }

    public function testServiceEtMetierAbsentsSontNull(): void
    {
        $personnel = $this->createPersonnel('Dr Martin', null, null);

        $data = $this->request('GET', '/api/personnel/'.$personnel->getId());

        $this->assertNull($data['service']);
        $this->assertNull($data['metier']);
    }

    public function testCreationRecopieLibelleServiceEtMetierDepuisLAd(): void
    {
        $this->adAccount('pdurand', 'Paul', 'Durand', department: 'Cardiologie médicale', title: 'Infirmier');

        $data = $this->request('POST', '/api/personnel', ['username' => 'pdurand'], admin: true);

        $this->assertStatus(201);
        $this->assertSame('Paul Durand', $data['libelle']);
        $this->assertSame(['id' => 'Cardiologie médicale', 'libelle' => 'Cardiologie médicale'], $data['service']);
        $this->assertSame(['id' => 'Infirmier', 'libelle' => 'Infirmier'], $data['metier']);
        $traces = $this->request('GET', '/api/traces', admin: true);
        $this->assertSame('Création du personnel de garde #'.$data['id'], $traces[0]['actionRealise']);
    }

    public function testCreationIgnoreLesChampsEnvoyesAutresQueLIdentifiant(): void
    {
        $this->adAccount('pdurand', 'Paul', 'Durand', department: 'Cardiologie', title: 'Infirmier');

        $data = $this->request('POST', '/api/personnel', ['username' => 'pdurand', 'libelle' => 'Pirate', 'service' => 'Faux', 'serviceId' => 1, 'metier' => 'Faux'], admin: true);

        $this->assertStatus(201);
        $this->assertSame('Paul Durand', $data['libelle']);
        $this->assertSame('Cardiologie', $data['service']['libelle']);
        $this->assertSame('Infirmier', $data['metier']['libelle']);
    }

    public function testCreationSansServiceNiPosteDansLAd(): void
    {
        $this->adAccount('pdurand', 'Paul', 'Durand');

        $data = $this->request('POST', '/api/personnel', ['username' => 'pdurand'], admin: true);

        $this->assertStatus(201);
        $this->assertNull($data['service']);
        $this->assertNull($data['metier']);
    }

    public function testCreationSansIdentifiantAdRenvoie422(): void
    {
        $data = $this->request('POST', '/api/personnel', ['libelle' => 'Saisi à la main'], admin: true);

        $this->assertStatus(422);
        $this->assertArrayHasKey('username', $data['errors']);
    }

    public function testCreationAvecIdentifiantAdInconnuRenvoie422(): void
    {
        $data = $this->request('POST', '/api/personnel', ['username' => 'inconnu'], admin: true);

        $this->assertStatus(422);
        $this->assertSame(['Identifiant AD introuvable.'], $data['errors']['username']);
        $this->assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM personnel_de_garde'));
    }

    public function testCreationDoublonRenvoie422(): void
    {
        $this->adAccount('pdurand', 'Paul', 'Durand');

        $this->request('POST', '/api/personnel', ['username' => 'pdurand'], admin: true);
        $this->assertStatus(201);

        $data = $this->request('POST', '/api/personnel', ['username' => 'PDURAND'], admin: true);
        $this->assertStatus(422);
        $this->assertArrayHasKey('username', $data['errors']);
    }

    public function testCreationAdInjoignableRenvoie503(): void
    {
        FakeDirectoryLookup::down();

        $this->request('POST', '/api/personnel', ['username' => 'pdurand'], admin: true);

        $this->assertStatus(503);
    }

    public function testModificationDeLIdentifiantRelitLAd(): void
    {
        $personnel = $this->createPersonnel('Dr Martin', 'Urgences', 'Médecin');
        $this->adAccount('autre', 'Anne', 'Bernard', department: 'Pédiatrie', title: 'Sage-femme');

        $data = $this->request('PATCH', '/api/personnel/'.$personnel->getId(), ['username' => 'autre'], admin: true);

        $this->assertStatus(200);
        $this->assertSame('Anne Bernard', $data['libelle']);
        $this->assertSame('Pédiatrie', $data['service']['libelle']);
        $this->assertSame('Sage-femme', $data['metier']['libelle']);
    }

    public function testLeLibelleLeServiceEtLeMetierNeSontJamaisModifiablesADirect(): void
    {
        $personnel = $this->createPersonnel('Dr Martin', 'Urgences', 'Médecin');

        $data = $this->request('PATCH', '/api/personnel/'.$personnel->getId(), ['libelle' => 'Pirate', 'service' => 'Faux', 'serviceId' => 99], admin: true);

        $this->assertStatus(200);
        $this->assertSame('Dr Martin', $data['libelle']);
        $this->assertSame('Urgences', $data['service']['libelle']);
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
        $this->createPersonnel('Alpha', 'Urgences', 'Médecin');
        $this->createPersonnel('Bravo', 'Urgences', 'Infirmier');
        $this->createPersonnel('Charlie', 'Pédiatrie', 'Médecin');

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

        // Recherche par mots (insensible à la casse) sur libellé, service, métier
        $this->assertSame(['Alpha'], array_column($this->request('GET', '/api/personnel?q=ALPHA'), 'libelle'));
        $this->assertSame(['Charlie'], array_column($this->request('GET', '/api/personnel?q=pédiatrie'), 'libelle'));
        $this->assertSame(['Alpha', 'Charlie'], array_column($this->request('GET', '/api/personnel?q=médecin'), 'libelle'));
        $this->assertSame(['Alpha'], array_column($this->request('GET', '/api/personnel?q=médecin+urgences'), 'libelle'));
        $this->assertSame([], $this->request('GET', '/api/personnel?q=zzz'));

        // Filtres : valeurs exactes du service et du métier
        $this->assertSame(['Alpha', 'Bravo'], array_column($this->request('GET', '/api/personnel?serviceId=Urgences'), 'libelle'));
        $this->assertSame(['Alpha', 'Charlie'], array_column($this->request('GET', '/api/personnel?metierId='.rawurlencode('Médecin')), 'libelle'));
        $this->assertSame(['Alpha'], array_column($this->request('GET', '/api/personnel?serviceId=Urgences&metierId='.rawurlencode('Médecin')), 'libelle'));
    }

    public function testListesDesServicesEtDesMetiersEnregistres(): void
    {
        $this->createPersonnel('Alpha', 'Urgences', 'Médecin');
        $this->createPersonnel('Bravo', 'Pédiatrie', 'Médecin');
        $this->createPersonnel('Charlie', null, null);

        $this->assertSame([['id' => 'Pédiatrie', 'libelle' => 'Pédiatrie'], ['id' => 'Urgences', 'libelle' => 'Urgences']], $this->request('GET', '/api/personnel/services'));
        $this->assertSame([['id' => 'Médecin', 'libelle' => 'Médecin']], $this->request('GET', '/api/personnel/metiers'));
    }

    public function testTriParNomOuParService(): void
    {
        $this->createPersonnel('Alpha', 'Urgences');
        $this->createPersonnel('Bravo', 'Pédiatrie');

        $this->assertSame(['Alpha', 'Bravo'], array_column($this->request('GET', '/api/personnel'), 'libelle'));
        $this->assertSame(['Alpha', 'Bravo'], array_column($this->request('GET', '/api/personnel?sort=nom'), 'libelle'));
        $this->assertSame(['Bravo', 'Alpha'], array_column($this->request('GET', '/api/personnel?sort=service'), 'libelle'));

        $this->request('GET', '/api/personnel?sort=foo');
        $this->assertStatus(400);
    }

    public function testRechercheEchappeLesJokersLike(): void
    {
        $this->createPersonnel('Alpha');

        $this->assertSame([], $this->request('GET', '/api/personnel?q=%25'));
        $this->assertSame([], $this->request('GET', '/api/personnel?q=_lpha'));
    }

    public function testParametresDeRechercheInvalides(): void
    {
        foreach (['limit=0', 'limit=101', 'limit=abc', 'page=0', 'page=9223372036854775807', 'q='.str_repeat('a', 51), 'serviceId='.str_repeat('a', 201)] as $qs) {
            $this->request('GET', '/api/personnel?'.$qs);
            $this->assertStatus(400);
        }
    }
}
