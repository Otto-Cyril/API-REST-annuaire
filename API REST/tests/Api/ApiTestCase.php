<?php

namespace App\Tests\Api;

use App\Entity\Garde;
use App\Entity\NumeroGarde;
use App\Entity\PersonnelDeGarde;
use App\Security\AdminUser;
use App\Tests\Support\FakeDirectoryLookup;
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
    private int $accounts = 0;

    protected function setUp(): void
    {
        FakeDirectoryLookup::reset();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Ordre : les tables enfants d'abord (clés étrangères).
        $connection = $this->em->getConnection();
        foreach (['garde', 'numero_garde', 'personnel_de_garde', 'numero_urgence', 'trace'] as $table) {
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

    /**
     * Personnel de garde enregistré ; le service et le métier sont des libellés (copies de l'AD).
     */
    protected function createPersonnel(string $libelle, ?string $service = 'Urgences', ?string $metier = 'Médecin'): PersonnelDeGarde
    {
        $personnel = (new PersonnelDeGarde())
            ->setUsername('compte'.++$this->accounts)
            ->setLibelle($libelle)
            ->setService($service)
            ->setMetier($metier);
        $this->em->persist($personnel);
        $this->em->flush();

        return $personnel;
    }

    /**
     * Compte AD simulé que l'API retrouvera à la création.
     */
    protected function adAccount(string $username, string $prenom = 'Jean', string $nom = 'Dupont', ?string $email = null, ?string $department = null, ?string $title = null): void
    {
        FakeDirectoryLookup::add($username, $prenom, $nom, $email, $department, $title);
    }

    protected function createNumeroGarde(PersonnelDeGarde $personnel, string $numero = '0102030405', string $type = 'Mobile'): NumeroGarde
    {
        $numeroGarde = (new NumeroGarde())->setNumero($numero)->setType($type)->setPersonnelDeGarde($personnel);
        $this->em->persist($numeroGarde);
        $this->em->flush();

        return $numeroGarde;
    }

    protected function createGarde(PersonnelDeGarde $personnel, string $debut = 'today', ?string $fin = null): Garde
    {
        $garde = (new Garde())
            ->setPersonnelDeGarde($personnel)
            ->setDateDebut(new \DateTimeImmutable($debut))
            ->setDateFin(new \DateTimeImmutable($fin ?? $debut));
        $this->em->persist($garde);
        $this->em->flush();

        return $garde;
    }

    protected function countTraces(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM trace');
    }
}
