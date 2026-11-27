<?php

namespace App\Tests;

use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Mail\MailHeader;
use App\Mail\MailHtml;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\FakeReaderFactory;

/** F-16 : un mail mis en forme s'affiche, mais rien d'actif ni d'espion ne passe. */
final class MailHtmlTest extends ApiTestCase
{
    protected function tearDown(): void
    {
        FakeReaderFactory::$html = null;
        FakeReaderFactory::$inline = [];
        FakeReaderFactory::$body = 'Corps simulé';
        parent::tearDown();
    }

    private function render(string $html, array $inline = [], bool $images = false): array
    {
        return (new MailHtml())->render($html, $inline, $images) ?? ['html' => '', 'blockedImages' => 0];
    }

    public function testKeepsTheLayoutAndDropsEverythingActive(): void
    {
        $out = $this->render(<<<'HTML'
            <html><head><title>x</title><style>body{background:url(https://spy/x)}</style><meta http-equiv="refresh" content="0;url=https://evil"></head>
            <body bgcolor="#ffffff"><script>alert(1)</script><iframe src="https://evil"></iframe><form action="https://evil"><input name="p"></form>
            <table width="600"><tr><td style="color:#333; background-image:url(https://spy/p.png); position:fixed; font-size:14px !important">
            <p onclick="alert(1)" class="a" id="b">Bonjour <b>David</b></p><svg><script>alert(2)</script></svg><unknown>texte gardé</unknown></td></tr></table></body></html>
            HTML)['html'];

        self::assertStringContainsString('<table width="600">', $out);
        self::assertStringContainsString('<p>Bonjour <b>David</b></p>', $out);
        self::assertStringContainsString('style="color: #333; font-size: 14px"', $out);
        self::assertStringContainsString('texte gardé', $out);
        self::assertStringContainsString('background-color: #ffffff', $out);
        foreach (['script', 'iframe', 'form', 'input', 'onclick', 'url(', 'position', 'alert', 'evil', 'spy', 'class=', 'id=', '<svg', 'meta'] as $banned) {
            self::assertStringNotContainsStringIgnoringCase($banned, $out, $banned);
        }
    }

    public function testLinksAreWebOnlyOpenElsewhereAndShowTheirRealAddress(): void
    {
        $out = $this->render('<a href="javascript:alert(1)">a</a> <a href="data:text/html,x">b</a> <a href="/relatif">c</a> <a href="https://x.fr/?a=1&amp;b=2" title="https://banque.fr">d</a>')['html'];

        self::assertStringNotContainsString('javascript', $out);
        self::assertStringNotContainsString('data:', $out);
        self::assertStringNotContainsString('relatif', $out);
        self::assertStringNotContainsString('banque', $out);
        self::assertStringContainsString('href="https://x.fr/?a=1&amp;b=2" target="_blank" rel="noopener noreferrer nofollow" title="https://x.fr/?a=1&amp;b=2"', $out);
    }

    public function testRemoteImagesWaitForTheReaderAndSpyPixelsVanish(): void
    {
        $html = '<img src="https://x.fr/logo.png" alt="Logo"><img src="https://spy.fr/p.gif" width="1" height="1"><img src="//cdn.fr/b.png">';
        $blocked = $this->render($html);
        self::assertSame(2, $blocked['blockedImages']);
        self::assertStringNotContainsString('src=', $blocked['html']);
        self::assertStringContainsString('[Logo]', $blocked['html']);

        $shown = $this->render($html, [], true);
        self::assertSame(0, $shown['blockedImages']);
        self::assertStringContainsString('src="https://x.fr/logo.png" referrerpolicy="no-referrer"', $shown['html']);
        self::assertStringContainsString('src="https://cdn.fr/b.png"', $shown['html']);
        self::assertStringNotContainsString('spy.fr', $shown['html']);
    }

    public function testImagesCarriedByTheMessageAreShownButNeverSvg(): void
    {
        $out = $this->render('<img src="cid:logo@x"><img src="cid:dessin@x" alt="Plan">', [
            'logo@x' => ['type' => 'image/png', 'content' => 'PNG'],
            'dessin@x' => ['type' => 'image/svg+xml', 'content' => '<svg/>'],
        ])['html'];

        self::assertStringContainsString('src="data:image/png;base64,'.base64_encode('PNG').'"', $out);
        self::assertStringNotContainsString('svg', $out);
        self::assertStringContainsString('[Plan]', $out);
    }

    public function testTextStaysTextAndOversizedOrEmptyMessagesFallBack(): void
    {
        self::assertStringContainsString('&lt;script&gt;', $this->render('<p>&lt;script&gt;</p>')['html']);
        self::assertNull((new MailHtml())->render(str_repeat('<p>a</p>', 100_000)));
        self::assertNull((new MailHtml())->render('<script>alert(1)</script>'));
        self::assertStringContainsString('au fond', $this->render(str_repeat('<div>', 200).'au fond'.str_repeat('</div>', 200))['html']);
    }

    public function testTheBodyGivesTextAndSafeLayoutAndImagesOnlyOnRequest(): void
    {
        $david = $this->user('david');
        $account = (new MailAccount())->setOwner($david)->setLabel('IUT')->setAddress('d@x.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('d');
        $this->em->persist($account);
        $mail = Mail::fromHeader($account, new MailHeader('<h@x>', null, [], 'Sophie', 's@x.fr', [], 'Lettre', new \DateTimeImmutable('2026-11-19 09:00:00'), false, 7, 'INBOX'));
        $this->em->persist($mail);
        $this->em->flush();
        $token = $this->tokenFor($david);
        FakeReaderFactory::$body = "Bonjour\n\nÀ bientôt";
        FakeReaderFactory::$html = '<p>Bonjour</p><script>x</script><img src="https://x.fr/a.png">';

        [$st, $r] = $this->call('GET', '/api/mails/'.$mail->getId().'/body', $token);
        self::assertSame([200, ['Bonjour', 'À bientôt'], '<p>Bonjour</p>', 1], [$st, $r['paragraphs'], $r['html'], $r['blockedImages']]);

        $r = $this->call('GET', '/api/mails/'.$mail->getId().'/body?images=1', $token)[1];
        self::assertSame(0, $r['blockedImages']);
        self::assertStringContainsString('https://x.fr/a.png', $r['html']);

        FakeReaderFactory::$html = null;
        self::assertNull($this->call('GET', '/api/mails/'.$mail->getId().'/body', $token)[1]['html']);
    }
}
