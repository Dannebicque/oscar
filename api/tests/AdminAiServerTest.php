<?php

namespace App\Tests;

use App\Ai\AiProviderRegistry;
use App\Entity\AuditEntry;
use App\Entity\InstanceSetting;
use App\Tests\Support\ApiTestCase;

/** §5 : le modèle de l'établissement, réglé par l'administrateur. Clé chiffrée et jamais renvoyée, destination connue de chacun et journalisée. */
final class AdminAiServerTest extends ApiTestCase
{
    private const SERVER = ['kind' => 'openai', 'baseUrl' => 'https://ia.univ.example/v1/', 'model' => 'llama3.1', 'apiKey' => 'CLE-SECRETE', 'hostedByUs' => true];

    private function registry(): AiProviderRegistry
    {
        $this->em->clear();

        return static::getContainer()->get(AiProviderRegistry::class);
    }

    public function testOnlyTheAdministratorSetsItAndTheKeyNeverComesBack(): void
    {
        $admin = $this->tokenFor($this->user('admin')->setInstanceAdmin(true));
        $member = $this->tokenFor($this->user('marie'));
        $this->em->flush();

        self::assertSame(403, $this->call('PUT', '/api/admin/ai-server', $member, self::SERVER)[0]);
        self::assertSame(422, $this->call('PUT', '/api/admin/ai-server', $admin, ['hostedByUs' => false] + self::SERVER)[0], 'sans confirmation, rien');
        self::assertSame(422, $this->call('PUT', '/api/admin/ai-server', $admin, ['baseUrl' => 'ftp://x'] + self::SERVER)[0]);
        self::assertNull($this->registry()->institution());

        [$st, $r] = $this->call('PUT', '/api/admin/ai-server', $admin, self::SERVER);
        self::assertSame([200, true, true, 'https://ia.univ.example/v1'], [$st, $r['configured'], $r['hasKey'], $r['baseUrl']]);
        foreach ([$r, $this->call('GET', '/api/admin/ai-server', $admin)[1], $this->call('GET', '/api/admin/config', $admin)[1], $this->call('GET', '/api/instance')[1]] as $shown) {
            self::assertStringNotContainsString('CLE-SECRETE', json_encode($shown));
        }
        self::assertStringNotContainsString('CLE-SECRETE', json_encode($this->em->find(InstanceSetting::class, 'aiServer')->getValue()), 'chiffrée au repos');

        // Chacun sait où partent ses textes ; l'adresse n'est pas publique.
        self::assertSame('ia.univ.example', $this->call('GET', '/api/ai-config', $member)[1]['institutionHost']);
        self::assertStringNotContainsString('univ.example', json_encode($this->call('GET', '/api/instance')[1]));
        self::assertSame('institution:openai', $this->registry()->for('university')->name());
        self::assertNull($this->registry()->for('off'));
        $log = array_map(static fn (AuditEntry $e) => $e->toArray(), $this->em->getRepository(AuditEntry::class)->findAll());
        self::assertContains(['ai.server', 'ia.univ.example'], array_map(static fn (array $a) => [$a['action'], $a['target']], $log));
    }

    public function testTheKeyDoesNotFollowToAnotherServerAndRemovingGoesBackToLocalRules(): void
    {
        $admin = $this->tokenFor($this->user('admin')->setInstanceAdmin(true));
        $this->em->flush();
        $this->call('PUT', '/api/admin/ai-server', $admin, self::SERVER);
        $without = ['model' => 'autre'] + self::SERVER;
        unset($without['apiKey']);
        self::assertTrue($this->call('PUT', '/api/admin/ai-server', $admin, $without)[1]['hasKey'], 'même serveur : la clé reste');
        self::assertFalse($this->call('PUT', '/api/admin/ai-server', $admin, ['baseUrl' => 'https://ailleurs.example/v1'] + $without)[1]['hasKey']);

        self::assertSame(204, $this->call('DELETE', '/api/admin/ai-server', $admin)[0]);
        self::assertNull($this->registry()->institution());
    }
}
