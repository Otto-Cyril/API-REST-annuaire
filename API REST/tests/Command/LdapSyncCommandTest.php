<?php

namespace App\Tests\Command;

use App\Entity\PersonnelDeGarde;
use App\Tests\Api\ApiTestCase;
use App\Tests\Support\FakeDirectoryLookup;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class LdapSyncCommandTest extends ApiTestCase
{
    private function sync(array $options = []): CommandTester
    {
        $application = new Application(static::$kernel);
        $tester = new CommandTester($application->find('app:ldap:sync'));
        $tester->execute($options);

        return $tester;
    }

    private function libelle(PersonnelDeGarde $personnel): ?string
    {
        $this->em->clear();

        return $this->em->getRepository(PersonnelDeGarde::class)->find($personnel->getId())->getLibelle();
    }

    public function testMetAJourLeLibelleDepuisLAd(): void
    {
        $personnel = $this->createPersonnel('Ancien libellé');
        $personnel->setUsername('jdupont');
        $this->em->flush();
        $this->adAccount('jdupont', 'Jean', 'Dupont');

        $tester = $this->sync();

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame('Jean Dupont', $this->libelle($personnel));
        $this->assertStringContainsString('1 personnel(s) de garde mis à jour', $tester->getDisplay());
    }

    public function testMetAJourLeServiceEtLeMetierDepuisLAd(): void
    {
        $personnel = $this->createPersonnel('Jean Dupont', 'Ancien service', 'Ancien poste');
        $personnel->setUsername('jdupont');
        $this->em->flush();
        $this->adAccount('jdupont', 'Jean', 'Dupont', department: 'Cardiologie médicale', title: 'Infirmier');

        $this->sync();

        $this->em->clear();
        $fiche = $this->em->getRepository($personnel::class)->find($personnel->getId());
        $this->assertSame(['Cardiologie médicale', 'Infirmier'], [$fiche->getService(), $fiche->getMetier()]);
    }

    public function testSimulationNeModifiePasLaBase(): void
    {
        $personnel = $this->createPersonnel('Ancien libellé');
        $personnel->setUsername('jdupont');
        $this->em->flush();
        $this->adAccount('jdupont', 'Jean', 'Dupont');

        $tester = $this->sync(['--dry-run' => true]);

        $this->assertStringContainsString('simulation', $tester->getDisplay());
        $this->assertSame('Ancien libellé', $this->libelle($personnel));
    }

    public function testCompteIntrouvableEstSignaleMaisConserve(): void
    {
        $personnel = $this->createPersonnel('Dr Parti');
        $personnel->setUsername('parti');
        $this->em->flush();

        $tester = $this->sync();

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('parti', $tester->getDisplay());
        $this->assertSame('Dr Parti', $this->libelle($personnel));
    }

    public function testAdInjoignableEchoueSansRienModifier(): void
    {
        $personnel = $this->createPersonnel('Ancien libellé');
        FakeDirectoryLookup::down();

        $tester = $this->sync();

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Annuaire LDAP indisponible.', $tester->getDisplay());
        $this->assertSame('Ancien libellé', $this->libelle($personnel));
    }
}
