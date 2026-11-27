<?php

namespace App\Tests;

use App\Entity\AiCall;
use App\Entity\User;
use App\Entity\UserSettings;
use App\Tests\Support\ApiTestCase;

/** §5 et rôle administrateur : des indicateurs d'usage de l'IA, jamais un contenu ni une personne. */
final class AdminAiUsageTest extends ApiTestCase
{
    private function journal(User $u, string $provider, string $purpose, ?bool $byModel): void
    {
        $this->em->persist((new AiCall($provider, $purpose, 'mail SECRET-ID, objet de 12 caractères', ['summary' => 'CONTENU PRIVÉ'], $byModel))->setOwner($u));
    }

    public function testOnlyAnAdministratorSeesNumbersAndNothingElse(): void
    {
        $admin = $this->user('admin')->setInstanceAdmin(true);
        $marie = $this->user('marie');
        $this->journal($marie, 'heuristic', 'mail-hints', false);
        $this->journal($marie, 'external:anthropic', 'mail-hints', true);
        $this->journal($marie, 'external:anthropic', 'reply-draft', false);
        $this->journal($admin, 'external:openai', 'relance-draft', null);
        $settings = new UserSettings($marie);
        $settings->apply(['aiMode' => 'external']);
        $this->em->persist($settings);
        $this->em->flush();

        self::assertSame(403, $this->call('GET', '/api/admin/ai-usage', $this->tokenFor($marie))[0]);
        [$st, $r] = $this->call('GET', '/api/admin/ai-usage', $this->tokenFor($admin));

        self::assertSame(200, $st);
        self::assertSame(['total' => 4, 'model' => 1, 'rules' => 1, 'fallback' => 1, 'unknown' => 1], $r['totals']);
        self::assertSame([2, 2, 1], [$r['activeUsers'], $r['users'], $r['modes']['external']]);
        self::assertEqualsCanonicalizing(['mail-hints', 'reply-draft', 'relance-draft'], array_column($r['features'], 'feature'));
        $json = json_encode($r, \JSON_UNESCAPED_UNICODE);
        foreach (['SECRET-ID', 'PRIVÉ', 'marie', 'objet'] as $private) {
            self::assertStringNotContainsStringIgnoringCase($private, $json, $private);
        }
        self::assertSame(30, $this->call('GET', '/api/admin/ai-usage?days=100000', $this->tokenFor($admin))[1]['days']);
    }
}
