<?php

namespace App\Tests;

use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Entity\Suivi;
use App\Entity\User;
use App\Mail\AliasRouter;
use App\Mail\MailHeader;
use App\Tests\Support\ApiTestCase;

/** F-40 : créer une boîte partagée, gérer ses membres, sans jamais capter les mails personnels de quelqu'un. */
final class SharedMailboxManagementTest extends ApiTestCase
{
    private User $david;
    private User $claire;
    private string $d;
    private string $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->david = $this->user('david')->setEmail('david@iut.fr');
        $this->claire = $this->user('claire')->setEmail('claire@iut.fr');
        $this->em->flush();
        $this->d = $this->tokenFor($this->david);
        $this->c = $this->tokenFor($this->claire);
    }

    private function box(string $address = 'contact@iut.fr'): array
    {
        [$st, $b] = $this->call('POST', '/api/shared-mailboxes', $this->d, ['address' => $address, 'name' => 'Accueil']);
        self::assertSame(201, $st, json_encode($b));

        return $b;
    }

    private function account(User $u, string $address): MailAccount
    {
        $u = $this->em->find(User::class, $u->getId()); // les requêtes précédentes ont vidé le gestionnaire d'entités
        $a = (new MailAccount())->setOwner($u)->setLabel('IUT')->setAddress($address)->setImapHost('h')->setSmtpHost('h')->setUsername('x');
        $this->em->persist($a);
        $this->em->flush();

        return $a;
    }

    public function testTheShortNameComesFromTheAddressAndTheCreatorAdministers(): void
    {
        $b = $this->box();
        self::assertSame(['contact', 'admin'], [$b['slug'], $b['role']]);
        self::assertSame('contact-2', $this->box('contact@autre.fr')['slug']);
    }

    public function testSomeoneElsesAddressCanNeverBecomeASharedMailbox(): void
    {
        $this->account($this->claire, 'claire.roux@iut.fr');
        foreach (['claire@iut.fr', 'Claire.Roux@iut.fr'] as $address) {
            self::assertSame(422, $this->call('POST', '/api/shared-mailboxes', $this->d, ['address' => $address, 'name' => 'Piège'])[0], $address);
        }
    }

    public function testAnAddressConnectedLaterIsStillNeverRouted(): void
    {
        $b = $this->box('prenom.nom@iut.fr');
        self::assertSame(201, $this->call('POST', '/api/shared-mailboxes/'.$b['id'].'/members', $this->d, ['username' => 'claire'])[0]);
        $account = $this->account($this->claire, 'prenom.nom@iut.fr');
        $mail = Mail::fromHeader($account, new MailHeader('<p@x>', null, [], 'Marc', 'm@x.fr', ['prenom.nom@iut.fr'], 'Privé', new \DateTimeImmutable(), false, 1));
        $this->em->persist($mail);

        self::assertNull(static::getContainer()->get(AliasRouter::class)->route($this->claire, $mail));
        self::assertSame('personal', $mail->getMailbox());
    }

    public function testAddingByEmailNotifiesAndTheMemberCanLeave(): void
    {
        $b = $this->box();
        [$st, $r] = $this->call('POST', '/api/shared-mailboxes/'.$b['id'].'/members', $this->d, ['username' => 'CLAIRE@iut.fr']);
        self::assertSame(201, $st);
        self::assertCount(2, $r['members']);
        self::assertSame('sharedAdded', $this->call('GET', '/api/notifications', $this->c)[1][0]['kind']);

        $mail = Mail::fromHeader($this->account($this->claire, 'c.perso@iut.fr'), new MailHeader('<q@x>', null, [], 'Marc', 'm@x.fr', ['contact@iut.fr'], 'Question', new \DateTimeImmutable(), false, 1));
        $this->em->persist($mail);
        static::getContainer()->get(AliasRouter::class)->route($mail->getOwner(), $mail);
        $this->em->flush();
        self::assertSame('contact', $mail->getMailbox());

        self::assertSame(403, $this->call('DELETE', '/api/shared-mailboxes/'.$b['id'].'/members/david', $this->c)[0], 'seul un administrateur retire quelqu’un d’autre');
        self::assertSame(204, $this->call('DELETE', '/api/shared-mailboxes/'.$b['id'].'/members/claire', $this->c)[0]);
        self::assertSame([], $this->call('GET', '/api/shared-mailboxes', $this->c)[1]);
        $this->em->clear();
        self::assertSame('personal', $this->em->find(Mail::class, $mail->getId())->getMailbox(), 'son mail reste visible chez elle');
    }

    public function testThereIsAlwaysAnAdministrator(): void
    {
        $b = $this->box();
        self::assertSame(409, $this->call('POST', '/api/shared-mailboxes/'.$b['id'].'/members', $this->d, ['username' => 'david', 'role' => 'member'])[0]);
        self::assertSame(409, $this->call('DELETE', '/api/shared-mailboxes/'.$b['id'].'/members/david', $this->d)[0]);
        self::assertSame(422, $this->call('POST', '/api/shared-mailboxes/'.$b['id'].'/members', $this->d, ['username' => 'personne'])[0]);
        self::assertSame(404, $this->call('DELETE', '/api/shared-mailboxes/'.$b['id'], $this->c)[0]);
    }

    public function testAnEmptyBoxCanBeDeletedButNeverOneWithDemands(): void
    {
        $empty = $this->box('erreur@iut.fr');
        self::assertSame(204, $this->call('DELETE', '/api/shared-mailboxes/'.$empty['id'], $this->d)[0]);

        $b = $this->box();
        $mail = Mail::fromHeader($this->account($this->david, 'd.perso@iut.fr'), new MailHeader('<r@x>', null, [], 'Marc', 'm@x.fr', ['contact@iut.fr'], 'Question', new \DateTimeImmutable(), false, 1));
        $this->em->persist($mail);
        static::getContainer()->get(AliasRouter::class)->route($mail->getOwner(), $mail);
        $this->em->flush();
        self::assertSame(409, $this->call('DELETE', '/api/shared-mailboxes/'.$b['id'], $this->d)[0]);
        self::assertSame(1, $this->em->getRepository(Suivi::class)->count([]));
    }
}
