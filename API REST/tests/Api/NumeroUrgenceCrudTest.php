<?php

namespace App\Tests\Api;

use App\Entity\NumeroUrgence;

class NumeroUrgenceCrudTest extends ApiTestCase
{
    private function creer(string $libelle = 'SAMU', string $numero = '15'): NumeroUrgence
    {
        $n = (new NumeroUrgence())->setLibelle($libelle)->setNumero($numero);
        $this->em->persist($n);
        $this->em->flush();

        return $n;
    }

    public function testListeEtAffichage(): void
    {
        $n = $this->creer();

        $this->assertCount(1, $this->request('GET', '/api/numeros-urgence'));
        $data = $this->request('GET', '/api/numeros-urgence/'.$n->getId());

        $this->assertStatus(200);
        $this->assertSame(['id' => $n->getId(), 'libelle' => 'SAMU', 'numero' => '15'], $data);
    }

    public function testCreationEtTrace(): void
    {
        $data = $this->request('POST', '/api/numeros-urgence', ['libelle' => 'Pompiers', 'numero' => '18'], admin: true);

        $this->assertStatus(201);
        $traces = $this->request('GET', '/api/traces', admin: true);
        $this->assertSame("Création du numéro d'urgence #".$data['id'], $traces[0]['actionRealise']);
    }

    public function testCreationInvalide(): void
    {
        $data = $this->request('POST', '/api/numeros-urgence', ['libelle' => 'Pompiers'], admin: true);

        $this->assertStatus(422);
        $this->assertArrayHasKey('numero', $data['errors']);
    }

    public function testModificationEtSuppression(): void
    {
        $n = $this->creer();

        $data = $this->request('PATCH', '/api/numeros-urgence/'.$n->getId(), ['numero' => '112'], admin: true);
        $this->assertStatus(200);
        $this->assertSame('112', $data['numero']);
        $this->assertSame('SAMU', $data['libelle']);

        $this->request('DELETE', '/api/numeros-urgence/'.$n->getId(), admin: true);
        $this->assertStatus(204);
        $this->request('GET', '/api/numeros-urgence/'.$n->getId());
        $this->assertStatus(404);
    }
}
