<?php

namespace App\Tests\Api;

use App\Entity\Metier;
use App\Entity\NumeroGarde;
use App\Entity\PersonnelDeGarde;
use App\Entity\Service;
use App\Security\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base des tests fonctionnels : client HTTP, base de test vidée avant chaque test,
 * JWT admin généré localement (aucun appel LDAP).
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Ordre : les tables enfants d'abord (clés étrangères).
        $connection = $this->em->getConnection();
        foreach (['numero_garde', 'personnel_de_garde', 'service', 'metier', 'numero_urgence', 'trace'] as $table) {
            $connection->executeStatement('DELETE FROM '.$table);
        }
    }

    protected function adminToken(): string
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class)->create(new AdminUser('testeur'));
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<mixed>|null corps de la réponse décodé
     */
    protected function request(string $method, string $uri, ?array $body = null, bool $admin = false, ?string $rawBody = null): ?array
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($admin) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$this->adminToken();
        }

        // Chaque requête HTTP réelle repart d'un EntityManager vide : sans cela l'identity map
        // du test (collections inverses non alimentées, etc.) fausserait le comportement de l'API.
        $this->em->clear();
        $this->client->request($method, $uri, [], [], $server, $rawBody ?? (null === $body ? null : json_encode($body)));

        $content = $this->client->getResponse()->getContent();

        return '' === $content ? null : json_decode($content, true);
    }

    protected function assertStatus(int $expected): void
    {
        $this->assertSame($expected, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());
    }

    protected function createService(string $libelle = 'Urgences', string $localisation = 'Bâtiment A'): Service
    {
        $service = (new Service())->setLibelle($libelle)->setLocalisation($localisation);
        $this->em->persist($service);
        $this->em->flush();

        return $service;
    }

    protected function createMetier(string $libelle = 'Médecin', string $nom = 'Dupont', string $prenom = 'Jean'): Metier
    {
        $metier = (new Metier())->setLibelle($libelle)->setNom($nom)->setPrenom($prenom);
        $this->em->persist($metier);
        $this->em->flush();

        return $metier;
    }

    protected function createPersonnel(string $libelle, ?Service $service = null, ?Metier $metier = null): PersonnelDeGarde
    {
        $personnel = (new PersonnelDeGarde())
            ->setLibelle($libelle)
            ->setService($service ?? $this->createService())
            ->setMetier($metier ?? $this->createMetier());
        $this->em->persist($personnel);
        $this->em->flush();

        return $personnel;
    }

    protected function createNumeroGarde(PersonnelDeGarde $personnel, string $numero = '0102030405', string $type = 'Mobile'): NumeroGarde
    {
        $numeroGarde = (new NumeroGarde())->setNumero($numero)->setType($type)->setPersonnelDeGarde($personnel);
        $this->em->persist($numeroGarde);
        $this->em->flush();

        return $numeroGarde;
    }

    protected function countTraces(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM trace');
    }
}
