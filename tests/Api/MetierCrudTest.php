<?php

namespace App\Tests\Api;

class MetierCrudTest extends ApiTestCase
{
    public function testListeEtAffichage(): void
    {
        $metier = $this->createMetier('Médecin', 'Dupont', 'Jean');

        $liste = $this->request('GET', '/api/metiers');
        $this->assertStatus(200);
        $this->assertCount(1, $liste);

        $data = $this->request('GET', '/api/metiers/'.$metier->getId());
        $this->assertStatus(200);
        $this->assertSame(['id' => $metier->getId(), 'libelle' => 'Médecin', 'nom' => 'Dupont', 'prenom' => 'Jean'], $data);
    }

    public function testCreationEtTrace(): void
    {
        $data = $this->request('POST', '/api/metiers', ['libelle' => 'Infirmier', 'nom' => 'Durand', 'prenom' => 'Claire'], admin: true);

        $this->assertStatus(201);
        $this->assertSame('Claire', $data['prenom']);
        $traces = $this->request('GET', '/api/traces', admin: true);
        $this->assertSame('Création du métier #'.$data['id'], $traces[0]['actionRealise']);
    }

    public function testCreationSansPrenomRenvoie422(): void
    {
        $data = $this->request('POST', '/api/metiers', ['libelle' => 'Infirmier', 'nom' => 'Durand'], admin: true);

        $this->assertStatus(422);
        $this->assertArrayHasKey('prenom', $data['errors']);
    }

    public function testModification(): void
    {
        $metier = $this->createMetier();

        $data = $this->request('PUT', '/api/metiers/'.$metier->getId(), ['nom' => 'Martin'], admin: true);

        $this->assertStatus(200);
        $this->assertSame('Martin', $data['nom']);
        $this->assertSame('Jean', $data['prenom']);
    }

    public function testSuppressionEtConflit(): void
    {
        $libre = $this->createMetier('Libre');
        $utilise = $this->createMetier('Utilisé');
        $this->createPersonnel('Dr X', null, $utilise);

        $this->request('DELETE', '/api/metiers/'.$libre->getId(), admin: true);
        $this->assertStatus(204);

        $this->request('DELETE', '/api/metiers/'.$utilise->getId(), admin: true);
        $this->assertStatus(409);
    }

    public function testIntrouvable(): void
    {
        $this->request('GET', '/api/metiers/999999');
        $this->assertStatus(404);
    }
}
