<?php

namespace App\Tests\Api;

use App\Entity\Metier;
use App\Entity\Personne;
use App\Entity\Service;

class PersonneCrudTest extends ApiTestCase
{
    private function createPersonne(string $nom, string $prenom, ?Service $service = null, ?Metier $metier = null, ?string $email = null, ?string $telephone = null): Personne
    {
        $personne = (new Personne())
            ->setNom($nom)
            ->setPrenom($prenom)
            ->setEmail($email)
            ->setTelephone($telephone)
            ->setService($service ?? $this->createService())
            ->setMetier($metier ?? $this->createMetier());
        $this->em->persist($personne);
        $this->em->flush();

        return $personne;
    }

    public function testAffichageAvecServiceEtMetier(): void
    {
        $service = $this->createService('Urgences', 'Bâtiment A');
        $metier = $this->createMetier('Médecin');
        $personne = $this->createPersonne('Martin', 'Claire', $service, $metier, 'claire.martin@imm.fr', '1234');

        $data = $this->request('GET', '/api/personnes/'.$personne->getId());

        $this->assertStatus(200);
        $this->assertSame('Martin', $data['nom']);
        $this->assertSame('Claire', $data['prenom']);
        $this->assertSame('claire.martin@imm.fr', $data['email']);
        $this->assertSame('1234', $data['telephone']);
        $this->assertSame('Urgences', $data['service']['libelle']);
        $this->assertSame('Médecin', $data['metier']['libelle']);
    }

    public function testCreationAvecEmailEtTelephoneOptionnels(): void
    {
        $service = $this->createService();
        $metier = $this->createMetier();

        $data = $this->request('POST', '/api/personnes', [
            'nom' => 'Durand',
            'prenom' => 'Paul',
            'serviceId' => $service->getId(),
            'metierId' => $metier->getId(),
        ], admin: true);

        $this->assertStatus(201);
        $this->assertNull($data['email']);
        $this->assertNull($data['telephone']);
        $traces = $this->request('GET', '/api/traces', admin: true);
        $this->assertSame('Création de la personne #'.$data['id'], $traces[0]['actionRealise']);
    }

    public function testCreationSansAdminRenvoie401(): void
    {
        $this->request('POST', '/api/personnes', ['nom' => 'X', 'prenom' => 'Y']);

        $this->assertStatus(401);
    }

    public function testValidation(): void
    {
        $data = $this->request('POST', '/api/personnes', ['nom' => '', 'prenom' => 'Paul', 'email' => 'pas-un-email'], admin: true);

        $this->assertStatus(422);
        $this->assertArrayHasKey('nom', $data['errors']);
        $this->assertArrayHasKey('email', $data['errors']);
        $this->assertArrayHasKey('service', $data['errors']);
        $this->assertArrayHasKey('metier', $data['errors']);
    }

    public function testCreationAvecRelationsInvalidesRenvoie400(): void
    {
        $metier = $this->createMetier();

        foreach ([999999, 'abc', null, true, 0, -1] as $bad) {
            $this->request('POST', '/api/personnes', ['nom' => 'X', 'prenom' => 'Y', 'serviceId' => $bad, 'metierId' => $metier->getId()], admin: true);
            $this->assertStatus(400);
        }
    }

    public function testModificationPartielle(): void
    {
        $personne = $this->createPersonne('Martin', 'Claire', email: 'a@imm.fr');
        $autre = $this->createService('Pédiatrie');

        $data = $this->request('PATCH', '/api/personnes/'.$personne->getId(), ['serviceId' => $autre->getId(), 'email' => null], admin: true);

        $this->assertStatus(200);
        $this->assertSame('Pédiatrie', $data['service']['libelle']);
        $this->assertNull($data['email']);
        $this->assertSame('Martin', $data['nom']);
    }

    public function testSuppression(): void
    {
        $personne = $this->createPersonne('Martin', 'Claire');
        $id = $personne->getId();

        $this->request('DELETE', '/api/personnes/'.$id, admin: true);
        $this->assertStatus(204);

        $this->request('GET', '/api/personnes/'.$id);
        $this->assertStatus(404);
    }

    public function testSuppressionDuServiceUtiliseRenvoie409(): void
    {
        $service = $this->createService();
        $this->createPersonne('Martin', 'Claire', $service);

        $this->request('DELETE', '/api/services/'.$service->getId(), admin: true);

        $this->assertStatus(409);
    }

    public function testRechercheTriEtPagination(): void
    {
        $urgences = $this->createService('Urgences', 'Bâtiment A');
        $pediatrie = $this->createService('Pédiatrie', 'Bâtiment B');
        $medecin = $this->createMetier('Médecin');
        $infirmier = $this->createMetier('Infirmier');
        $this->createPersonne('Alpha', 'Zoé', $urgences, $medecin, 'zoe@imm.fr', '1111');
        $this->createPersonne('Bravo', 'Yann', $urgences, $infirmier, null, '2222');
        $this->createPersonne('Charlie', 'Xavier', $pediatrie, $medecin);

        $noms = fn (string $qs) => array_column($this->request('GET', '/api/personnes'.$qs), 'nom');

        // Tri par nom (défaut) et pagination avec en-têtes
        $this->assertSame(['Alpha', 'Bravo'], $noms('?limit=2&page=1'));
        $this->assertSame(['Charlie'], $noms('?limit=2&page=2'));
        $headers = $this->client->getResponse()->headers;
        $this->assertSame('3', $headers->get('X-Total-Count'));
        $this->assertSame('2', $headers->get('X-Total-Pages'));

        // Tri par service (Pédiatrie avant Urgences)
        $this->assertSame(['Charlie', 'Alpha', 'Bravo'], $noms('?sort=service'));

        // Recherche par mots sur nom, prénom, e-mail, téléphone, service, métier
        $this->assertSame(['Alpha'], $noms('?q=ALPHA'));
        $this->assertSame(['Alpha'], $noms('?q=zoé+alpha'));
        $this->assertSame(['Alpha'], $noms('?q=zoe@imm'));
        $this->assertSame(['Bravo'], $noms('?q=2222'));
        $this->assertSame(['Charlie'], $noms('?q=pédiatrie'));
        $this->assertSame(['Alpha', 'Charlie'], $noms('?q=médecin'));
        $this->assertSame([], $noms('?q=zzz'));

        // Filtres
        $this->assertSame(['Alpha', 'Bravo'], $noms('?serviceId='.$urgences->getId()));
        $this->assertSame(['Alpha'], $noms('?serviceId='.$urgences->getId().'&metierId='.$medecin->getId()));
    }

    public function testRechercheEchappeLesJokersLike(): void
    {
        $this->createPersonne('Alpha', 'Zoé');

        $this->assertSame([], $this->request('GET', '/api/personnes?q=%25'));
        $this->assertSame([], $this->request('GET', '/api/personnes?q=_lpha'));
    }

    public function testParametresInvalides(): void
    {
        foreach (['limit=0', 'limit=101', 'page=0', 'serviceId=x', 'metierId=-1', 'sort=foo', 'q='.str_repeat('a', 51)] as $qs) {
            $this->request('GET', '/api/personnes?'.$qs);
            $this->assertStatus(400);
        }
    }
}
