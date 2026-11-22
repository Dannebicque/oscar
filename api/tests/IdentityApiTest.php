<?php

namespace App\Tests;

use App\Entity\Identity;
use App\Entity\MailAccount;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\FakeOutgoingMailer;
use App\Tests\Support\FakeReaderFactory;

/** F-19 : identités d'expéditeur et signatures. */
final class IdentityApiTest extends ApiTestCase
{
    private string $token;
    private MailAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        FakeReaderFactory::$appended = [];
        $david = $this->user('david');
        $this->account = (new MailAccount())->setOwner($david)->setLabel('IUT')->setAddress('david@iut-troyes.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('david')->setSignature('-- David');
        $this->em->persist($this->account);
        $this->em->flush();
        $this->token = $this->tokenFor($david);
    }

    public function testEachAccountGetsAPrimaryIdentityFromItsAddressAndSignature(): void
    {
        [$st, $list] = $this->call('GET', '/api/identities', $this->token);
        self::assertSame(200, $st);
        self::assertCount(1, $list);
        self::assertSame(['david@iut-troyes.fr', true, true, '-- David'], [$list[0]['address'], $list[0]['primary'], $list[0]['isDefault'], $list[0]['signature']]);
        self::assertCount(1, $this->call('GET', '/api/identities', $this->token)[1], 'pas de doublon à la lecture suivante');
    }

    public function testCreateUpdateDefaultAndDelete(): void
    {
        $primary = $this->call('GET', '/api/identities', $this->token)[1][0];
        [$st, $alias] = $this->call('POST', '/api/identities', $this->token, ['accountId' => $this->account->getId(), 'name' => 'Direction des études', 'address' => 'Etudes@IUT-Troyes.fr', 'replyTo' => 'secretariat@iut-troyes.fr', 'signature' => 'Direction des études']);
        self::assertSame([201, 'etudes@iut-troyes.fr', false], [$st, $alias['address'], $alias['primary']]);

        [$st, $d] = $this->call('POST', '/api/identities/'.$alias['id'].'/default', $this->token);
        self::assertSame([200, true], [$st, $d['isDefault']]);
        $defaults = array_filter($this->call('GET', '/api/identities', $this->token)[1], fn ($x) => $x['isDefault']);
        self::assertCount(1, $defaults, 'une seule identité par défaut');

        [$st, $u] = $this->call('PUT', '/api/identities/'.$alias['id'], $this->token, ['name' => 'Études', 'address' => 'etudes@iut-troyes.fr', 'replyTo' => '', 'signature' => '']);
        self::assertSame([200, 'Études', null, null], [$st, $u['name'], $u['replyTo'], $u['signature']]);

        [$st, $p] = $this->call('PUT', '/api/identities/'.$primary['id'], $this->token, ['name' => 'David A.', 'address' => 'autre@x.fr', 'signature' => 'Nouveau']);
        self::assertSame([200, 'david@iut-troyes.fr', 'David A.'], [$st, $p['address'], $p['name']], 'l’adresse de l’identité principale reste celle du compte');

        self::assertSame(409, $this->call('DELETE', '/api/identities/'.$primary['id'], $this->token)[0], 'la principale ne se supprime pas');
        self::assertSame(204, $this->call('DELETE', '/api/identities/'.$alias['id'], $this->token)[0]);
        $after = $this->call('GET', '/api/identities', $this->token)[1];
        self::assertSame([1, true], [\count($after), $after[0]['isDefault']], 'la principale redevient l’identité par défaut');
    }

    public function testValidationIsolationAndOtherAccounts(): void
    {
        $bad = ['accountId' => $this->account->getId(), 'name' => 'X', 'address' => 'pas une adresse'];
        self::assertSame(422, $this->call('POST', '/api/identities', $this->token, $bad)[0]);
        self::assertSame(422, $this->call('POST', '/api/identities', $this->token, ['name' => '', 'address' => 'a@b.fr'] + $bad)[0]);
        self::assertSame(422, $this->call('POST', '/api/identities', $this->token, ['name' => 'A<b>', 'address' => 'a@b.fr', 'accountId' => $this->account->getId()])[0]);
        self::assertSame(422, $this->call('POST', '/api/identities', $this->token, ['name' => 'A', 'address' => 'a@b.fr', 'replyTo' => 'x', 'accountId' => $this->account->getId()])[0]);
        self::assertSame(422, $this->call('POST', '/api/identities', $this->token, ['name' => 'A', 'address' => 'a@b.fr', 'signature' => str_repeat('x', 2001), 'accountId' => $this->account->getId()])[0]);

        $alias = $this->call('POST', '/api/identities', $this->token, ['accountId' => $this->account->getId(), 'name' => 'A', 'address' => 'a@b.fr'])[1];
        $other = $this->tokenFor($this->user('claire'));
        self::assertSame([], $this->call('GET', '/api/identities', $other)[1]);
        self::assertSame(404, $this->call('PUT', '/api/identities/'.$alias['id'], $other, ['name' => 'Z', 'address' => 'a@b.fr'])[0]);
        self::assertSame(404, $this->call('DELETE', '/api/identities/'.$alias['id'], $other)[0]);
        self::assertSame(404, $this->call('POST', '/api/identities', $other, ['accountId' => $this->account->getId(), 'name' => 'Vol', 'address' => 'v@x.fr'])[0], 'pas d’identité sur le compte d’un autre');
    }

    public function testSendingUsesTheChosenIdentityAndRefusesAForeignOne(): void
    {
        $smtp = static::getContainer()->get(FakeOutgoingMailer::class);
        $alias = $this->call('POST', '/api/identities', $this->token, ['accountId' => $this->account->getId(), 'name' => 'Direction des études', 'address' => 'etudes@iut-troyes.fr', 'replyTo' => 'secretariat@iut-troyes.fr'])[1];
        $base = ['accountId' => $this->account->getId(), 'to' => ['marc@x.fr'], 'subject' => 'Bonjour', 'body' => 'Texte'];

        self::assertSame(201, $this->call('POST', '/api/mails/send', $this->token, $base + ['identityId' => $alias['id']])[0]);
        $mail = end($smtp->sent);
        self::assertSame(['etudes@iut-troyes.fr', 'Direction des études'], [$mail->getFrom()[0]->getAddress(), $mail->getFrom()[0]->getName()]);
        self::assertSame('secretariat@iut-troyes.fr', $mail->getReplyTo()[0]->getAddress());

        self::assertSame(201, $this->call('POST', '/api/mails/send', $this->token, $base)[0]);
        self::assertSame('david@iut-troyes.fr', end($smtp->sent)->getFrom()[0]->getAddress(), 'sans identité : l’adresse du compte, comme avant');

        $claire = $this->user('claire');
        $acc2 = (new MailAccount())->setOwner($claire)->setLabel('X')->setAddress('c@x.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('c');
        $this->em->persist($acc2);
        $this->em->flush();
        $theirs = $this->call('GET', '/api/identities', $this->tokenFor($claire))[1][0];
        [$st] = $this->call('POST', '/api/mails/send', $this->token, $base + ['identityId' => $theirs['id']]);
        self::assertSame(422, $st, 'l’identité d’un autre utilisateur est refusée');
        self::assertSame(1, $this->em->getRepository(Identity::class)->count(['owner' => $claire]));
    }

    public function testHtmlSignatureIsSanitizedStoredWithItsTextAndPreviewed(): void
    {
        $html = '<p style="color:#336699;background:url(x)">David <b>Annebicque</b></p><script>alert(1)</script><a href="javascript:x()">piège</a><a href="https://iut-troyes.fr">Site</a><img src="http://x/a.png">';
        [$st, $p] = $this->call('POST', '/api/identities/preview', $this->token, ['html' => $html]);
        self::assertSame(200, $st);
        self::assertStringNotContainsString('script', $p['html']);
        self::assertStringNotContainsString('javascript', $p['html']);
        self::assertStringNotContainsString('url(', $p['html']);
        self::assertStringNotContainsString('http://x', $p['html'], 'image non https refusée');
        self::assertStringContainsString('https://iut-troyes.fr', $p['html']);
        self::assertStringStartsWith("-- \nDavid Annebicque", $p['text']);
        self::assertSame(422, $this->call('POST', '/api/identities/preview', $this->token, ['html' => str_repeat('<b>x</b>', 3000)])[0]);

        $id = $this->call('GET', '/api/identities', $this->token)[1][0]['id'];
        [$st, $u] = $this->call('PUT', '/api/identities/'.$id, $this->token, ['name' => 'David', 'signatureHtml' => $html]);
        self::assertSame(200, $st);
        self::assertStringNotContainsString('script', $u['signatureHtml']);
        self::assertStringStartsWith("-- \nDavid Annebicque", $u['signature'], 'le texte est dérivé du HTML');
        [, $back] = $this->call('PUT', '/api/identities/'.$id, $this->token, ['name' => 'David', 'signature' => 'Texte simple', 'signatureHtml' => '']);
        self::assertSame([null, 'Texte simple'], [$back['signatureHtml'], $back['signature']], 'repasser en texte efface le HTML');
        self::assertSame(422, $this->call('PUT', '/api/identities/'.$id, $this->token, ['name' => 'David', 'signatureHtml' => '<script>x()</script>'])[0]);
    }

    public function testAnHtmlSignatureIsSentAsMultipartWithTheTextSignatureReplaced(): void
    {
        $smtp = static::getContainer()->get(FakeOutgoingMailer::class);
        $id = $this->call('GET', '/api/identities', $this->token)[1][0]['id'];
        $sig = $this->call('PUT', '/api/identities/'.$id, $this->token, ['name' => 'David', 'signatureHtml' => '<b>David</b> · <a href="https://iut-troyes.fr">IUT</a>'])[1]['signature'];
        $base = ['accountId' => $this->account->getId(), 'to' => ['marc@x.fr'], 'subject' => 'Bonjour', 'identityId' => $id];

        self::assertSame(201, $this->call('POST', '/api/mails/send', $this->token, $base + ['body' => "Bonjour <Marc> & merci\n\n".$sig])[0]);
        $mail = end($smtp->sent);
        self::assertStringContainsString('<b>David</b>', (string) $mail->getHtmlBody());
        self::assertStringContainsString('Bonjour &lt;Marc&gt; &amp; merci', (string) $mail->getHtmlBody(), 'le texte de l’utilisateur est échappé');
        self::assertStringContainsString('Bonjour <Marc> & merci', (string) $mail->getTextBody());

        self::assertSame(201, $this->call('POST', '/api/mails/send', $this->token, $base + ['body' => 'Signature retirée à la main'])[0]);
        self::assertNull(end($smtp->sent)->getHtmlBody(), 'signature retouchée ou retirée : pas de partie HTML');
    }
}
