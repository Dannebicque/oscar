<?php

namespace App\Tests;

use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Mail\MailHeader;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\FakeReaderFactory;

/** F-16 : dossiers et sous-dossiers de la messagerie. Serveur d'abord, jamais de suppression de messages. */
final class MailFolderApiTest extends ApiTestCase
{
    private MailAccount $account;
    private Mail $mail;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        FakeReaderFactory::$moved = [];
        FakeReaderFactory::$moveFails = false;
        FakeReaderFactory::$folders = [];
        FakeReaderFactory::$serverCount = [];
        FakeReaderFactory::$renamed = [];
        FakeReaderFactory::$recent = [];
        $david = $this->user('david');
        $this->account = (new MailAccount())->setOwner($david)->setLabel('IUT')->setAddress('d@x.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('d');
        $this->em->persist($this->account);
        $this->mail = Mail::fromHeader($this->account, new MailHeader('<m@x>', null, [], 'Sophie', 's@x.fr', [], 'Planning', new \DateTimeImmutable('2026-11-19 09:00:00'), false, 7, 'INBOX'));
        $this->em->persist($this->mail);
        $this->em->flush();
        $this->token = $this->tokenFor($david);
    }

    public function testCreateListNestRenameAndMoveIntoAFolder(): void
    {
        [$st, $f] = $this->call('POST', '/api/mail-folders', $this->token, ['name' => 'Projets']);
        self::assertSame([201, 'Projets'], [$st, $f['path']]);
        [$st, $sub] = $this->call('POST', '/api/mail-folders', $this->token, ['name' => 'Jury', 'parent' => 'Projets']);
        self::assertSame([201, 'Projets/Jury'], [$st, $sub['path']], 'sous-dossier créé sous son parent');
        $list = $this->call('GET', '/api/mail-folders', $this->token)[1];
        $custom = array_column(array_filter($list, fn ($x) => !$x['system']), 'path');
        self::assertSame(['Projets', 'Projets/Jury'], array_values($custom));
        self::assertContains('INBOX', array_column(array_filter($list, fn ($x) => $x['system']), 'path'));

        [$st, $r] = $this->call('POST', '/api/mails/'.$this->mail->getId().'/move', $this->token, ['folder' => 'Projets/Jury']);
        self::assertSame([200, 'Projets/Jury'], [$st, $r['folder']]);
        self::assertSame([['INBOX', 7, 'Projets/Jury']], FakeReaderFactory::$moved);

        [$st] = $this->call('PATCH', '/api/mail-folders', $this->token, ['path' => 'Projets', 'name' => 'Jurys']);
        self::assertSame(200, $st);
        self::assertSame([['Projets', 'Jurys']], FakeReaderFactory::$renamed);
        $this->em->clear();
        self::assertSame('Jurys/Jury', $this->em->find(Mail::class, $this->mail->getId())->getFolder(), 'les messages suivent le renommage');
    }

    public function testMoveToAnUnknownFolderIsRefusedAndNothingIsCreated(): void
    {
        [$st] = $this->call('POST', '/api/mails/'.$this->mail->getId().'/move', $this->token, ['folder' => 'Nimporte']);
        self::assertSame(422, $st);
        self::assertSame([], FakeReaderFactory::$moved);
        self::assertSame(422, $this->call('POST', '/api/mails/'.$this->mail->getId().'/move', $this->token, ['folder' => 'Sent'])[0], 'les dossiers de base passent par leur voie habituelle');
    }

    public function testValidationAndSystemFoldersAreProtected(): void
    {
        foreach (['', 'a/b', 'a.b', str_repeat('x', 61), 'a%'] as $bad) {
            self::assertSame(422, $this->call('POST', '/api/mail-folders', $this->token, ['name' => $bad])[0], "nom refusé : « $bad »");
        }
        self::assertSame(409, $this->call('POST', '/api/mail-folders', $this->token, ['name' => 'Archive'])[0], 'nom déjà pris par un dossier de base');
        $this->call('POST', '/api/mail-folders', $this->token, ['name' => 'Perso']);
        self::assertSame(409, $this->call('POST', '/api/mail-folders', $this->token, ['name' => 'perso'])[0], 'doublon, sans tenir compte de la casse');
        self::assertSame(422, $this->call('PATCH', '/api/mail-folders', $this->token, ['path' => 'INBOX', 'name' => 'Autre'])[0]);
        self::assertSame(422, $this->call('DELETE', '/api/mail-folders?path=Trash', $this->token)[0]);
        self::assertSame(422, $this->call('POST', '/api/mail-folders', $this->token, ['name' => 'Sous', 'parent' => 'INBOX'])[0], 'pas de sous-dossier sous un dossier de base');
        self::assertSame(401, $this->call('GET', '/api/mail-folders')[0]);
    }

    public function testADeletedFolderMustBeEmptyEverywhere(): void
    {
        $this->call('POST', '/api/mail-folders', $this->token, ['name' => 'Vide']);
        $this->call('POST', '/api/mail-folders', $this->token, ['name' => 'Plein']);
        $this->call('POST', '/api/mail-folders', $this->token, ['name' => 'Parent']);
        $this->call('POST', '/api/mail-folders', $this->token, ['name' => 'Enfant', 'parent' => 'Parent']);
        FakeReaderFactory::$serverCount['Plein'] = 3;

        self::assertSame(409, $this->call('DELETE', '/api/mail-folders?path=Plein', $this->token)[0], 'des messages sur le serveur');
        self::assertSame(409, $this->call('DELETE', '/api/mail-folders?path=Parent', $this->token)[0], 'des sous-dossiers');
        $this->call('POST', '/api/mails/'.$this->mail->getId().'/move', $this->token, ['folder' => 'Vide']);
        self::assertSame(409, $this->call('DELETE', '/api/mail-folders?path=Vide', $this->token)[0], 'des messages connus d’Oscar');
        $this->call('POST', '/api/mails/'.$this->mail->getId().'/move', $this->token, ['folder' => 'INBOX']);
        self::assertSame(204, $this->call('DELETE', '/api/mail-folders?path=Vide', $this->token)[0]);
        self::assertNotContains('Vide', FakeReaderFactory::$folders);
        self::assertSame(404, $this->call('DELETE', '/api/mail-folders?path=Fantome', $this->token)[0]);
    }

    public function testOpeningAFolderImportsItsLatestMessagesOnce(): void
    {
        $this->call('POST', '/api/mail-folders', $this->token, ['name' => 'Archives 2025']);
        FakeReaderFactory::$recent = [
            new MailHeader('<a@x>', null, [], 'Marc', 'm@x.fr', [], 'Ancien un', new \DateTimeImmutable('2025-05-01 09:00:00'), false, 1, 'Archives 2025'),
            new MailHeader('<b@x>', null, [], 'Marc', 'm@x.fr', [], 'Ancien deux', new \DateTimeImmutable('2025-05-02 09:00:00'), false, 2, 'Archives 2025'),
        ];
        [$st, $r] = $this->call('POST', '/api/mail-folders/sync', $this->token, ['path' => 'Archives 2025']);
        self::assertSame([200, 2], [$st, $r['fetched']]);
        self::assertSame(2, $this->em->getRepository(Mail::class)->count(['folder' => 'Archives 2025']));
        self::assertSame(404, $this->call('POST', '/api/mail-folders/sync', $this->token, ['path' => 'Fantome'])[0]);
    }

    public function testFoldersAreIsolatedPerUserAndAServerDownIsReported(): void
    {
        $other = $this->tokenFor($this->user('claire'));
        self::assertSame([], $this->call('GET', '/api/mail-folders', $other)[1], 'pas de compte : liste vide');
        self::assertSame(422, $this->call('POST', '/api/mail-folders', $other, ['name' => 'X'])[0]);
        $this->em->find(MailAccount::class, $this->account->getId())->setImapPort(1);
        $this->em->flush();
        self::assertSame(502, $this->call('GET', '/api/mail-folders', $this->token)[0]);
    }
}
