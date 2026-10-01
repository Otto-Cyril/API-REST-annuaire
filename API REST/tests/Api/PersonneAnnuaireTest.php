<?php

namespace App\Tests\Api;

use App\Tests\Support\FakeDirectoryLookup;

/**
 * Annuaire du personnel : lu directement dans l'AD (simulé), en lecture seule.
 */
class PersonneAnnuaireTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeDirectoryLookup::add('jdupont', 'Jean', 'Dupont', 'jean.dupont@imm.fr', 'Cardiologie', 'Médecin', [['numero' => '4412', 'type' => 'Tél.'], ['numero' => '+33140000000', 'type' => 'IP']]);
        FakeDirectoryLookup::add('mclaire', 'Claire', 'Martin', null, 'Urgences', 'Infirmier');
        FakeDirectoryLookup::add('lgarcia', 'Léa', 'Garcia', 'lea.garcia@imm.fr', 'Cardiologie', 'Infirmier');
        FakeDirectoryLookup::add('sansservice', 'Zoé', 'Zorro');
    }

    public function testListePubliqueTrieeParNomAvecEnTetesDePagination(): void
    {
        $data = $this->request('GET', '/api/personnes');

        $this->assertStatus(200);
        $this->assertSame(['jdupont', 'lgarcia', 'mclaire', 'sansservice'], array_column($data, 'username'));
        $response = $this->client->getResponse();
        $this->assertSame('4', $response->headers->get('X-Total-Count'));
        $this->assertSame('1', $response->headers->get('X-Total-Pages'));
    }

    public function testFormeDUnePersonne(): void
    {
        $data = $this->request('GET', '/api/personnes/jdupont');

        $this->assertStatus(200);
        $this->assertSame([
            'username' => 'jdupont',
            'prenom' => 'Jean',
            'nom' => 'Dupont',
            'email' => 'jean.dupont@imm.fr',
            'service' => ['id' => 'Cardiologie', 'libelle' => 'Cardiologie'],
            'metier' => ['id' => 'Médecin', 'libelle' => 'Médecin'],
            'numeros' => [['numero' => '4412', 'type' => 'Tél.'], ['numero' => '+33140000000', 'type' => 'IP']],
        ], $data);
    }

    public function testPersonneSansServiceNiPoste(): void
    {
        $data = $this->request('GET', '/api/personnes/sansservice');

        $this->assertNull($data['service']);
        $this->assertNull($data['metier']);
        $this->assertSame([], $data['numeros']);
    }

    public function testFicheIntrouvable(): void
    {
        $this->request('GET', '/api/personnes/inconnu');
        $this->assertStatus(404);
    }

    public function testRechercheParMotsSansCasseNiAccents(): void
    {
        $noms = fn (string $qs) => array_column($this->request('GET', '/api/personnes'.$qs), 'username');

        $this->assertSame(['lgarcia'], $noms('?q=lea'));            // accent
        $this->assertSame(['lgarcia'], $noms('?q=LÉA+garcia'));      // casse, plusieurs mots
        $this->assertSame(['jdupont', 'lgarcia'], $noms('?q=cardio')); // service
        $this->assertSame(['jdupont'], $noms('?q=4412'));            // numéro
        $this->assertSame(['jdupont'], $noms('?q=33140000000'));     // numéro d'un autre type
        $this->assertSame(['jdupont'], $noms('?q=jean.dupont@'));    // e-mail
        $this->assertSame([], $noms('?q=introuvable'));
    }

    public function testFiltresParServiceEtPoste(): void
    {
        $noms = fn (string $qs) => array_column($this->request('GET', '/api/personnes'.$qs), 'username');

        $this->assertSame(['jdupont', 'lgarcia'], $noms('?serviceId=Cardiologie'));
        $this->assertSame(['lgarcia', 'mclaire'], $noms('?metierId=Infirmier'));
        $this->assertSame(['lgarcia'], $noms('?serviceId=Cardiologie&metierId=Infirmier'));
    }

    public function testTriParService(): void
    {
        $data = $this->request('GET', '/api/personnes?sort=service');

        // Cardiologie, Urgences, puis les personnes sans service en dernier
        $this->assertSame(['jdupont', 'lgarcia', 'mclaire', 'sansservice'], array_column($data, 'username'));
    }

    public function testPagination(): void
    {
        $page2 = $this->request('GET', '/api/personnes?limit=3&page=2');

        $this->assertSame(['sansservice'], array_column($page2, 'username'));
        $this->assertSame('2', $this->client->getResponse()->headers->get('X-Total-Pages'));
    }

    public function testListesDesServicesEtDesPostes(): void
    {
        $this->assertSame([['id' => 'Cardiologie', 'libelle' => 'Cardiologie'], ['id' => 'Urgences', 'libelle' => 'Urgences']], $this->request('GET', '/api/personnes/services'));
        $this->assertSame([['id' => 'Infirmier', 'libelle' => 'Infirmier'], ['id' => 'Médecin', 'libelle' => 'Médecin']], $this->request('GET', '/api/personnes/metiers'));
    }

    public function testRepartitionParServiceEtParPoste(): void
    {
        $data = $this->request('GET', '/api/personnes/stats');

        $this->assertStatus(200);
        $this->assertSame(4, $data['total']);
        // plus fréquent d'abord, puis ordre alphabétique ; la personne sans service n'est pas comptée
        $this->assertSame([['libelle' => 'Cardiologie', 'total' => 2], ['libelle' => 'Urgences', 'total' => 1]], $data['services']);
        $this->assertSame([['libelle' => 'Infirmier', 'total' => 2], ['libelle' => 'Médecin', 'total' => 1]], $data['metiers']);
    }

    public function testParametresInvalides(): void
    {
        foreach (['?sort=x', '?limit=101', '?page=0', '?limit=abc', '?q='.str_repeat('a', 51)] as $qs) {
            $this->request('GET', '/api/personnes'.$qs);
            $this->assertStatus(400);
        }
    }

    public function testLEcritureNExistePlus(): void
    {
        foreach ([['POST', '/api/personnes'], ['PUT', '/api/personnes/jdupont'], ['DELETE', '/api/personnes/jdupont']] as [$method, $uri]) {
            $this->request($method, $uri, ['nom' => 'x'], admin: true);
            $this->assertContains($this->client->getResponse()->getStatusCode(), [404, 405]);
        }
    }

    public function testAdInjoignableRenvoie503(): void
    {
        FakeDirectoryLookup::down();

        $data = $this->request('GET', '/api/personnes');

        $this->assertStatus(503);
        $this->assertSame('Annuaire LDAP indisponible.', $data['message']);
    }
}
