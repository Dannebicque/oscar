<?php

namespace App\Tests;

use App\Entity\MailAccount;
use App\Entity\User;
use App\Instance\SetupToken;
use App\Tests\Support\ApiTestCase;

/** Configuration globale de l'instance, assistant de premier lancement et administrateur d'instance. */
final class InstanceTest extends ApiTestCase
{
    private function code(): string
    {
        return static::getContainer()->get(SetupToken::class)->ensure();
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(SetupToken::class)->discard();
        parent::tearDown();
    }

    private function install(array $over = []): array
    {
        return $this->call('POST', '/api/setup', null, $over + ['code' => $this->code(), 'name' => 'Ada Admin', 'email' => 'Ada@Exemple.fr', 'password' => 'mot-de-passe-long-1', 'config' => ['instanceName' => 'Mon Oscar', 'registration' => 'open']]);
    }

    public function testThePublicPageTellsWhetherTheSetupIsNeeded(): void
    {
        [$st, $i] = $this->call('GET', '/api/instance');
        self::assertSame(200, $st);
        self::assertSame(['Oscar', true, true, 10], [$i['name'], $i['setupNeeded'], $i['aiExternalAllowed'], $i['defaultAttachmentMb']]);
        self::assertArrayNotHasKey('setupDone', $i, 'rien d’interne n’est publié');
    }

    public function testTheSetupNeedsTheCodeAndIsThrottled(): void
    {
        $this->code();
        self::assertSame(403, $this->install(['code' => 'faux'])[0]);
        for ($i = 0; $i < 4; ++$i) {
            $this->install(['code' => 'faux']);
        }
        self::assertSame(429, $this->install(['code' => $this->code()])[0], 'cinq essais ratés bloquent même le bon code pendant une heure');
        self::assertSame(0, $this->em->getRepository(User::class)->count(['instanceAdmin' => true]));
    }

    public function testTheSetupCreatesTheAdministratorOnceAndSetsTheBasics(): void
    {
        [$st, $r] = $this->install();
        self::assertSame(201, $st);
        $token = $r['token'];
        [, $me] = $this->call('GET', '/api/me', $token);
        self::assertSame([true, 'ada@exemple.fr'], [$me['admin'], $me['email']]);
        [, $i] = $this->call('GET', '/api/instance');
        self::assertSame(['Mon Oscar', true, false], [$i['name'], $i['registration'], $i['setupNeeded']]);
        self::assertSame(409, $this->install()[0], 'une seule fois');
        self::assertSame(200, $this->call('POST', '/api/auth/login', null, ['email' => 'ada@exemple.fr', 'password' => 'mot-de-passe-long-1'])[0], 'connexion normale ensuite');
    }

    public function testTheSetupValidatesEverythingBeforeCreatingAnything(): void
    {
        foreach ([['email' => 'pas-une-adresse'], ['name' => ''], ['password' => 'court'], ['config' => ['registration' => 'peut-etre']], ['config' => ['instanceName' => '<script>']]] as $bad) {
            self::assertSame(422, $this->install($bad)[0], json_encode($bad));
        }
        self::assertSame(0, $this->em->getRepository(User::class)->count([]));
    }

    public function testOnlyTheAdministratorReadsAndChangesTheConfig(): void
    {
        $admin = $this->install()[1]['token'];
        $user = $this->tokenFor($this->user('claire'));
        self::assertSame(403, $this->call('GET', '/api/admin/config', $user)[0]);
        self::assertSame(403, $this->call('PUT', '/api/admin/config', $user, ['instanceName' => 'Pirate'])[0]);
        self::assertSame(401, $this->call('GET', '/api/admin/config')[0]);

        [$st, $c] = $this->call('PUT', '/api/admin/config', $admin, ['instanceName' => 'Cabinet Martin', 'supportEmail' => 'aide@exemple.fr', 'legalUrl' => 'https://exemple.fr/mentions', 'defaultAttachmentMb' => 20, 'inconnu' => 1]);
        self::assertSame([200, 'Cabinet Martin', 20], [$st, $c['instanceName'], $c['defaultAttachmentMb']]);
        self::assertArrayNotHasKey('inconnu', $c);
        [$st, $e] = $this->call('PUT', '/api/admin/config', $admin, ['legalUrl' => 'javascript:alert(1)', 'defaultAttachmentMb' => 99, 'aiExternalAllowed' => 'oui', 'instanceName' => 'OK']);
        self::assertSame(422, $st);
        self::assertSame(['legalUrl', 'defaultAttachmentMb', 'aiExternalAllowed'], array_keys($e['fields']));
        self::assertSame('Cabinet Martin', $this->call('GET', '/api/instance')[1]['name'], 'un échec n’enregistre rien');
    }

    public function testRegistrationFollowsTheInstanceSetting(): void
    {
        $admin = $this->install(['config' => ['registration' => 'closed']])[1]['token'];
        $new = ['name' => 'Nouvel', 'email' => 'nouvel@exemple.fr', 'password' => 'mot-de-passe-long-2'];
        self::assertSame(403, $this->call('POST', '/api/auth/register', null, $new)[0]);
        self::assertFalse($this->call('GET', '/api/auth/config')[1]['registration']);
        $this->call('PUT', '/api/admin/config', $admin, ['registration' => 'open']);
        self::assertTrue($this->call('GET', '/api/auth/config')[1]['registration']);
        self::assertSame(201, $this->call('POST', '/api/auth/register', null, $new)[0]);
    }

    public function testTheDefaultAttachmentLimitComesFromTheInstanceAndAnAccountOverridesIt(): void
    {
        $admin = $this->install()[1]['token'];
        $david = $this->user('david');
        $acc = (new MailAccount())->setOwner($david)->setLabel('IUT')->setAddress('d@x.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('d');
        $this->em->persist($acc);
        $this->em->flush();
        $t = $this->tokenFor($david);
        $file = ['name' => 'a.bin', 'type' => 'x', 'content' => base64_encode(str_repeat('a', 1_300_000))];
        $mail = ['accountId' => $acc->getId(), 'to' => ['m@x.fr'], 'subject' => 'S', 'body' => 'b', 'attachments' => [$file]];

        self::assertSame(201, $this->call('POST', '/api/mails/send', $t, $mail)[0], '1,3 Mo sous les 10 Mo par défaut');
        $this->call('PUT', '/api/admin/config', $admin, ['defaultAttachmentMb' => 1]);
        [$st, $e] = $this->call('POST', '/api/mails/send', $t, $mail);
        self::assertSame([413, 1], [$st, $e['limitMb']], 'la limite de l’instance s’applique');
        $this->em->find(MailAccount::class, $acc->getId())->setAttachmentLimitMb(5);
        $this->em->flush();
        self::assertSame(201, $this->call('POST', '/api/mails/send', $t, $mail)[0], 'la limite du compte prime');
    }

    public function testTheAdministratorCanForbidExternalAi(): void
    {
        $admin = $this->install()[1]['token'];
        $t = $this->tokenFor($this->user('claire'));
        $cfg = ['kind' => 'openai', 'baseUrl' => 'https://api.openai.com/v1', 'model' => 'gpt-4o-mini', 'apiKey' => 'sk-test'];
        self::assertSame(200, $this->call('PUT', '/api/ai-config', $t, $cfg)[0]);
        $this->call('PUT', '/api/admin/config', $admin, ['aiExternalAllowed' => false]);
        [$st, $e] = $this->call('PUT', '/api/ai-config', $t, $cfg);
        self::assertSame(403, $st);
        self::assertStringContainsString('n’autorise pas l’IA externe', $e['title']);
        self::assertFalse($this->call('GET', '/api/instance')[1]['aiExternalAllowed']);
    }
}
