<?php

namespace App\Tests\Api;

class GardeCrudTest extends ApiTestCase
{
    public function testGardeEnCoursFiltreParJourBornesIncluses(): void
    {
        $avant = $this->createPersonnel('Dr Hier');
        $pendant = $this->createPersonnel('Dr Aujourdhui');
        $this->createNumeroGarde($pendant, '0102030405', 'DECT');
        $fin = $this->createPersonnel('Dr Dernier jour');
        $apres = $this->createPersonnel('Dr Demain');
        $this->createGarde($avant, '2026-06-09');
        $this->createGarde($pendant, '2026-06-10', '2026-06-12');
        $this->createGarde($fin, '2026-06-01', '2026-06-10');
        $this->createGarde($apres, '2026-06-11');

        $data = $this->request('GET', '/api/gardes?date=2026-06-10');

        $this->assertStatus(200);
        $this->assertSame(['Dr Aujourdhui', 'Dr Dernier jour'], array_column(array_column($data, 'personnelDeGarde'), 'libelle'));
        $this->assertSame('2026-06-10', $data[0]['dateDebut']);
        $this->assertSame('2026-06-12', $data[0]['dateFin']);
        $this->assertSame('0102030405', $data[0]['personnelDeGarde']['numerosGarde'][0]['numero']);
    }

    public function testProchainesRenvoieLePremierJourFuturSeulement(): void
    {
        $passee = $this->createPersonnel('Dr Passee');
        $encours = $this->createPersonnel('Dr En cours');
        $a = $this->createPersonnel('Dr Bravo');
        $b = $this->createPersonnel('Dr Alpha');
        $plusTard = $this->createPersonnel('Dr Plus tard');
        $this->createGarde($passee, '2026-06-01', '2026-06-05');
        $this->createGarde($encours, '2026-06-08', '2026-06-12');
        $this->createGarde($a, '2026-06-15', '2026-06-20');
        $this->createGarde($b, '2026-06-15');
        $this->createGarde($plusTard, '2026-06-22');

        $data = $this->request('GET', '/api/gardes/prochaines?date=2026-06-10');

        $this->assertStatus(200);
        $this->assertSame(['Dr Alpha', 'Dr Bravo'], array_column(array_column($data, 'personnelDeGarde'), 'libelle'));
        $this->assertSame('2026-06-15', $data[0]['dateDebut']);
    }

    public function testProchainesExclutUneGardeCommencantLeJourDonne(): void
    {
        $this->createGarde($this->createPersonnel('Dr Martin'), '2026-06-10');

        $this->assertSame([], $this->request('GET', '/api/gardes/prochaines?date=2026-06-10'));
    }

    public function testProchainesSansGardeAVenirRenvoieListeVide(): void
    {
        $this->createGarde($this->createPersonnel('Dr Martin'), '2026-06-01');

        $this->assertSame([], $this->request('GET', '/api/gardes/prochaines?date=2026-06-10'));
        $this->assertStatus(200);
    }

    public function testProchainesExigeUneDateValide(): void
    {
        foreach (['/api/gardes/prochaines', '/api/gardes/prochaines?date=', '/api/gardes/prochaines?date=abc', '/api/gardes/prochaines?date=2026-02-30'] as $uri) {
            $this->request('GET', $uri);
            $this->assertStatus(400);
        }
    }

    public function testJourSansGardeRenvoieListeVide(): void
    {
        $this->createGarde($this->createPersonnel('Dr Martin'), '2026-06-10');

        $this->assertSame([], $this->request('GET', '/api/gardes?date=2026-06-20'));
        $this->assertStatus(200);
    }

    public function testListeSansDateRenvoieToutesLesGardes(): void
    {
        $personnel = $this->createPersonnel('Dr Martin');
        $this->createGarde($personnel, '2026-06-10');
        $this->createGarde($personnel, '2026-07-01');

        $data = $this->request('GET', '/api/gardes');

        $this->assertCount(2, $data);
        $this->assertSame('2026-07-01', $data[0]['dateDebut']);
    }

    public function testDateInvalideRenvoie400(): void
    {
        foreach (['abc', '2026-13-40', '2026-02-30', '10/06/2026'] as $bad) {
            $data = $this->request('GET', '/api/gardes?date='.urlencode($bad));
            $this->assertStatus(400);
            $this->assertSame('date invalide (format attendu : AAAA-MM-JJ).', $data['message']);
        }
    }

    public function testCreationEtTrace(): void
    {
        $personnel = $this->createPersonnel('Dr Martin');

        $data = $this->request('POST', '/api/gardes', [
            'personnelDeGardeId' => $personnel->getId(),
            'dateDebut' => '2026-06-10',
            'dateFin' => '2026-06-11',
        ], admin: true);

        $this->assertStatus(201);
        $this->assertSame($personnel->getId(), $data['personnelDeGarde']['id']);
        $this->assertSame('2026-06-11', $data['dateFin']);
        $traces = $this->request('GET', '/api/traces', admin: true);
        $this->assertSame('Création de la garde #'.$data['id'], $traces[0]['actionRealise']);
    }

    public function testCreationSansAdminRenvoie401(): void
    {
        $personnel = $this->createPersonnel('Dr Martin');

        $this->request('POST', '/api/gardes', ['personnelDeGardeId' => $personnel->getId(), 'dateDebut' => '2026-06-10', 'dateFin' => '2026-06-10']);

        $this->assertStatus(401);
    }

    public function testFinAvantDebutRenvoie422(): void
    {
        $personnel = $this->createPersonnel('Dr Martin');

        $data = $this->request('POST', '/api/gardes', [
            'personnelDeGardeId' => $personnel->getId(),
            'dateDebut' => '2026-06-11',
            'dateFin' => '2026-06-10',
        ], admin: true);

        $this->assertStatus(422);
        $this->assertArrayHasKey('dateFin', $data['errors']);
    }

    public function testDatesEtPersonnelObligatoires(): void
    {
        $data = $this->request('POST', '/api/gardes', [], admin: true);
        $this->assertStatus(422);
        $this->assertArrayHasKey('dateDebut', $data['errors']);
        $this->assertArrayHasKey('dateFin', $data['errors']);
        $this->assertArrayHasKey('personnelDeGarde', $data['errors']);
    }

    public function testCreationAvecPersonnelInvalideRenvoie400(): void
    {
        foreach ([999999, 'abc', null, false] as $bad) {
            $data = $this->request('POST', '/api/gardes', ['personnelDeGardeId' => $bad, 'dateDebut' => '2026-06-10', 'dateFin' => '2026-06-10'], admin: true);
            $this->assertStatus(400);
            $this->assertSame('personnelDeGardeId invalide.', $data['message']);
        }
    }

    public function testCreationAvecDateInvalideRenvoie400(): void
    {
        $personnel = $this->createPersonnel('Dr Martin');

        $this->request('POST', '/api/gardes', ['personnelDeGardeId' => $personnel->getId(), 'dateDebut' => 'pas une date', 'dateFin' => '2026-06-10'], admin: true);

        $this->assertStatus(400);
    }

    public function testModificationEtTrace(): void
    {
        $garde = $this->createGarde($this->createPersonnel('Dr Martin'), '2026-06-10');

        $data = $this->request('PATCH', '/api/gardes/'.$garde->getId(), ['dateFin' => '2026-06-15'], admin: true);

        $this->assertStatus(200);
        $this->assertSame('2026-06-10', $data['dateDebut']);
        $this->assertSame('2026-06-15', $data['dateFin']);
        $traces = $this->request('GET', '/api/traces', admin: true);
        $this->assertSame('Modification de la garde #'.$garde->getId(), $traces[0]['actionRealise']);
    }

    public function testSuppressionEtTrace(): void
    {
        $garde = $this->createGarde($this->createPersonnel('Dr Martin'), '2026-06-10');
        $id = $garde->getId();

        $this->request('DELETE', '/api/gardes/'.$id, admin: true);

        $this->assertStatus(204);
        $this->request('GET', '/api/gardes/'.$id);
        $this->assertStatus(404);
        $traces = $this->request('GET', '/api/traces', admin: true);
        $this->assertSame('Suppression de la garde #'.$id, $traces[0]['actionRealise']);
    }

    public function testSuppressionDuPersonnelSupprimeSesGardes(): void
    {
        $personnel = $this->createPersonnel('Dr Martin');
        $this->createGarde($personnel, '2026-06-10');

        $this->request('DELETE', '/api/personnel/'.$personnel->getId(), admin: true);

        $this->assertStatus(204);
        $this->assertSame([], $this->request('GET', '/api/gardes'));
    }
}
