<?php

namespace App\Tests\Api;

use App\Tests\Support\FakeDirectoryLookup;

class AdSearchTest extends ApiTestCase
{
    public function testRefuseeSansToken(): void
    {
        $this->request('GET', '/api/ad/recherche?q=dupont');

        $this->assertStatus(401);
    }

    public function testRechercheParMotsTrieeParNom(): void
    {
        $this->adAccount('jdupont', 'Jean', 'Dupont', 'jean.dupont@imm.fr');
        $this->adAccount('adupont', 'Anne', 'Dupont');
        $this->adAccount('mclaire', 'Claire', 'Martin');

        $data = $this->request('GET', '/api/ad/recherche?q=dupont', admin: true);

        $this->assertStatus(200);
        $this->assertSame(['adupont', 'jdupont'], array_column($data, 'username'));
        $this->assertSame(['username' => 'adupont', 'prenom' => 'Anne', 'nom' => 'Dupont', 'email' => null, 'libelle' => 'Anne Dupont'], $data[0]);

        $data = $this->request('GET', '/api/ad/recherche?q=jean+dup', admin: true);
        $this->assertSame(['jdupont'], array_column($data, 'username'));
    }

    public function testAucunResultat(): void
    {
        $this->assertSame([], $this->request('GET', '/api/ad/recherche?q=zzz', admin: true));
        $this->assertStatus(200);
    }

    public function testRequeteTropCourteOuTropLongue(): void
    {
        foreach (['', 'a', str_repeat('a', 51)] as $q) {
            $this->request('GET', '/api/ad/recherche?q='.$q, admin: true);
            $this->assertStatus(400);
        }
    }

    public function testAdInjoignableRenvoie503(): void
    {
        FakeDirectoryLookup::down();

        $data = $this->request('GET', '/api/ad/recherche?q=dupont', admin: true);

        $this->assertStatus(503);
        $this->assertSame('Annuaire LDAP indisponible.', $data['message']);
    }
}
