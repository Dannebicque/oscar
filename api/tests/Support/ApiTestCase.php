<?php

namespace App\Tests\Support;

use App\Entity\User;
use App\Security\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Base des tests d'API : base en mémoire neuve, utilisateurs et jetons à la demande. */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        static::getContainer()->get('cache.app')->clear(); // compteurs d'essais et codes à usage unique repartent de zéro
        $this->client->disableReboot(); // sinon la base en mémoire disparaît entre deux requêtes
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConfiguration()->setUseSchemaEditorApi(true);
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($this->em->getMetadataFactory()->getAllMetadata()); // repart d'une base vide, aussi sur PostgreSQL
        $tool->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    protected function user(string $name): User
    {
        $u = new User($name, ucfirst($name));
        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }

    protected function tokenFor(User $u): string
    {
        return static::getContainer()->get(TokenService::class)->issue($u, 'test');
    }

    /** @return array{0: int, 1: mixed} statut et corps JSON décodé */
    protected function call(string $method, string $uri, ?string $token = null, ?array $json = null, string $contentType = 'application/json'): array
    {
        $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => $contentType];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->client->request($method, $uri, server: $server, content: $json === null ? null : json_encode($json));
        $body = $this->client->getResponse()->getContent();

        return [$this->client->getResponse()->getStatusCode(), $body === '' || $body === false ? null : json_decode($body, true)];
    }
}
