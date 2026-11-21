<?php

namespace App\Tests;

use App\Entity\AiProviderConfig;
use App\Tests\Support\ApiTestCase;

/** Configuration du fournisseur d'IA externe : la clé est chiffrée et ne revient jamais. */
final class AiConfigApiTest extends ApiTestCase
{
    public function testConfigRoundTripNeverReturnsTheKeyAndStoresItEncrypted(): void
    {
        $t = $this->tokenFor($this->user('david'));
        [, $c] = $this->call('GET', '/api/ai-config', $t);
        self::assertFalse($c['configured']);
        [$st, $c] = $this->call('PUT', '/api/ai-config', $t, ['kind' => 'openai', 'baseUrl' => 'https://api.openai.com/v1/', 'model' => 'gpt-4o-mini', 'apiKey' => 'sk-très-secret']);
        self::assertSame(200, $st);
        self::assertTrue($c['hasKey'] && $c['configured']);
        self::assertSame('https://api.openai.com/v1', $c['baseUrl']);
        self::assertStringNotContainsString('sk-très-secret', json_encode($c));
        $row = $this->em->getRepository(AiProviderConfig::class)->findAll()[0];
        self::assertStringNotContainsString('sk-très-secret', (string) $row->getApiKeyEncrypted(), 'chiffrée au repos');
        // Sans apiKey : on garde ; avec une chaîne vide : on retire.
        [, $c] = $this->call('PUT', '/api/ai-config', $t, ['kind' => 'openai', 'baseUrl' => 'https://api.openai.com/v1', 'model' => 'gpt-4o']);
        self::assertTrue($c['hasKey']);
        [, $c] = $this->call('PUT', '/api/ai-config', $t, ['kind' => 'openai', 'baseUrl' => 'https://api.openai.com/v1', 'model' => 'gpt-4o', 'apiKey' => '']);
        self::assertFalse($c['hasKey']);
    }

    public function testInvalidInputIsRefusedWithAClearMessageAndConfigsAreIsolated(): void
    {
        $t = $this->tokenFor($this->user('david'));
        [$st, $r] = $this->call('PUT', '/api/ai-config', $t, ['kind' => 'skynet', 'baseUrl' => 'https://x.fr', 'model' => 'm']);
        self::assertSame([422, 'kind'], [$st, $r['field']]);
        [$st, $r] = $this->call('PUT', '/api/ai-config', $t, ['kind' => 'openai', 'baseUrl' => 'pas-une-adresse', 'model' => 'm']);
        self::assertSame([422, 'baseUrl'], [$st, $r['field']]);
        [$st, $r] = $this->call('PUT', '/api/ai-config', $t, ['kind' => 'openai', 'baseUrl' => 'https://x.fr/v1', 'model' => '']);
        self::assertSame([422, 'model'], [$st, $r['field']]);
        $this->call('PUT', '/api/ai-config', $t, ['kind' => 'openai', 'baseUrl' => 'https://x.fr/v1', 'model' => 'm', 'apiKey' => 'k']);
        [, $other] = $this->call('GET', '/api/ai-config', $this->tokenFor($this->user('claire')));
        self::assertFalse($other['configured'], 'la configuration d’un utilisateur n’est pas visible d’un autre');
    }

    public function testExternalModeWithoutAConfigurationGivesNoProvider(): void
    {
        $registry = static::getContainer()->get(\App\Ai\AiProviderRegistry::class);
        $u = $this->user('david');
        self::assertNull($registry->for('external', $u));
        self::assertNull($registry->for('off', $u));
    }
}
