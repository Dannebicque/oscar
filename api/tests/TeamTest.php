<?php

namespace App\Tests;

use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Entity\Notification;
use App\Entity\SharedMailbox;
use App\Entity\Suivi;
use App\Entity\User;
use App\Mail\MailboxReader;
use App\Mail\MailHeader;
use App\Mail\MailSynchronizer;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\FakeOutgoingMailer;
use App\Tests\Support\SpyAiProvider;

final class TeamTest extends ApiTestCase
{
    private const ALIAS_ID = '<inscription-1@gmail.com>';
    /** @var array<string, User> */
    private array $users = [];
    /** @var array<string, MailAccount> */
    private array $accounts = [];
    private SharedMailbox $box;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['david', 'claire', 'marc', 'lea', 'outsider'] as $n) {
            $this->users[$n] = $this->user($n);
            $a = (new MailAccount())->setOwner($this->users[$n])->setLabel($n)->setAddress("$n@iut-troyes.fr")->setImapHost('h')->setSmtpHost('h')->setUsername($n);
            $this->em->persist($a);
            $this->accounts[$n] = $a;
        }
        $this->box = new SharedMailbox('Contact@iut-troyes.fr', 'Contact', 'contact');
        foreach (['david' => 'admin', 'claire' => 'member', 'marc' => 'member', 'lea' => 'member'] as $n => $role) {
            $this->box->addMember($this->users[$n], $role);
        }
        $this->em->persist($this->box);
        $this->em->flush();
    }

    private function header(string $id = self::ALIAS_ID, array $to = ['contact@iut-troyes.fr'], int $uid = 1): MailHeader
    {
        return new MailHeader($id, null, [], 'Camille Roux', 'camille@gmail.com', $to, 'Question d’inscription', new \DateTimeImmutable('2026-11-20 08:00:00'), false, $uid, 'INBOX');
    }

    private function receive(string $who, MailHeader $h): void
    {
        $reader = new class([$h]) implements MailboxReader {
            public function __construct(private array $all) {}
            public function fetchAfter(string $folder, int $afterUid): array { return $folder === 'INBOX' ? array_values(array_filter($this->all, fn ($h) => $h->uid > $afterUid)) : []; }
            public function fetchBody(string $folder, int $uid): ?string { return null; }
            public function invitation(string $folder, int $uid): ?string { return null; }
            public function attachments(string $folder, int $uid): array { return []; }
            public function attachment(string $folder, int $uid, int $index): ?array { return null; }
        };
        static::getContainer()->get(MailSynchronizer::class)->sync($this->accounts[$who], $reader);
    }

    private function tok(string $who): string
    {
        return $this->tokenFor($this->users[$who]);
    }

    private function team(): Suivi
    {
        $this->em->clear();

        return $this->em->getRepository(Suivi::class)->findOneBy(['mailId' => self::ALIAS_ID]);
    }

    private function claim(string $who, string $id, string $action): array
    {
        return $this->call('POST', "/api/suivis/$id/claim", $this->tok($who), ['action' => $action]);
    }

    public function testTheSameMessageInFourMailboxesIsOneDemand(): void
    {
        foreach (['david', 'claire', 'marc', 'lea'] as $n) {
            $this->receive($n, $this->header());
        }
        self::assertSame(1, $this->em->getRepository(Suivi::class)->count([]), 'F-44 : un seul suivi pour quatre exemplaires');
        self::assertSame(4, $this->em->getRepository(Mail::class)->count([]), 'chaque membre garde sa copie');
        $s = $this->team();
        self::assertNull($s->getOwner(), 'aucun responsable désigné');
        self::assertSame('contact', $s->getMailbox());
        self::assertSame(['received'], array_column($s->getJournal(), 'action'));

        foreach (['david', 'claire', 'marc', 'lea'] as $n) {
            [, $list] = $this->call('GET', '/api/suivis', $this->tok($n));
            self::assertSame(['Question d’inscription'], array_column($list, 'subject'), "$n voit la demande");
        }
        self::assertSame([], $this->call('GET', '/api/suivis', $this->tok('outsider'))[1], 'un non-membre ne voit rien');
        self::assertSame(404, $this->call('GET', '/api/suivis/'.$s->getId(), $this->tok('outsider'))[0]);
    }

    public function testOnlyMessagesToADeclaredAliasForAMemberAreRouted(): void
    {
        $this->receive('david', $this->header('<a@x>', ['david@iut-troyes.fr'], 1));
        $this->receive('outsider', $this->header('<b@x>', ['contact@iut-troyes.fr'], 1));
        self::assertSame(0, $this->em->getRepository(Suivi::class)->count([]), 'mail personnel, ou alias dont on n’est pas membre');
        self::assertNull($this->em->getRepository(Mail::class)->findOneBy(['messageId' => '<b@x>'])->getSharedMailbox());
        $this->receive('claire', $this->header('<c@x>', ['x@y.fr', 'CONTACT@iut-troyes.fr'], 1));
        self::assertSame(1, $this->em->getRepository(Suivi::class)->count([]), 'adresse de l’alias reconnue sans tenir compte de la casse, même en copie');
    }

    public function testAutoFollowRuleCanBeDisabled(): void
    {
        $this->box->setAutoFollow(false);
        $this->em->flush();
        $this->receive('david', $this->header());
        self::assertSame(0, $this->em->getRepository(Suivi::class)->count([]), 'F-66 : la règle est désactivable');
        self::assertSame('contact', $this->em->getRepository(Mail::class)->findOneBy([])->getMailbox());
    }

    public function testMembersMailsStayPrivate(): void
    {
        $this->receive('david', $this->header());
        $mail = $this->em->getRepository(Mail::class)->findOneBy([]);
        self::assertSame(404, $this->call('GET', '/api/mails/'.$mail->getId().'/body', $this->tok('claire'))[0]);
        self::assertSame([], $this->call('GET', '/api/mails', $this->tok('claire'))[1], '§2 : les mails privés ne sont jamais visibles des autres membres');
        self::assertCount(1, $this->call('GET', '/api/mails', $this->tok('david'))[1]);
        self::assertSame('contact', $this->call('GET', '/api/mails', $this->tok('david'))[1][0]['mailbox']);
    }

    public function testClaimFlowWithAntiDuplicateWarningAndTakeOver(): void
    {
        $this->receive('david', $this->header());
        $id = $this->team()->getId();

        [$st, $r] = $this->claim('david', $id, 'take');
        self::assertSame([200, 'inProgress', 'David'], [$st, $r['status'], $r['takenByName']]);

        [$st, $r] = $this->claim('claire', $id, 'take');
        self::assertSame(409, $st);
        self::assertStringContainsString('David s’en occupe', $r['title']);
        self::assertSame('David', $r['takenByName'], 'F-68 : avertissement avant de doublonner');

        [$st, $r] = $this->claim('claire', $id, 'takeOver');
        self::assertSame([200, 'Claire'], [$st, $r['takenByName']]);
        $notified = $this->em->getRepository(Notification::class)->findBy(['owner' => $this->users['david']]);
        self::assertSame('takenOver', $notified[0]->getKind(), 'l’ancien responsable est informé');
        self::assertSame('Claire', $notified[0]->getParams()['who']);

        self::assertSame('done', $this->claim('marc', $id, 'handle')[1]['status'], 'n’importe quel membre peut traiter');
        self::assertSame(409, $this->claim('lea', $id, 'take')[0], 'déjà traitée');
        self::assertSame('inProgress', $this->claim('lea', $id, 'reopen')[1]['status']);
        $r = $this->claim('lea', $id, 'release')[1];
        self::assertSame(['new', null], [$r['status'], $r['takenByName']]);
        self::assertSame(409, $this->claim('lea', $id, 'takeOver')[0], 'personne à reprendre');
        self::assertSame(422, $this->claim('lea', $id, 'voler')[0]);

        $actions = array_column($this->team()->getJournal(), 'action');
        self::assertSame(['received', 'take', 'takeOver:David', 'handled', 'reopen', 'release'], $actions, 'F-58 : journal complet, non modifiable');
    }

    public function testNonMembersCannotActOnADemand(): void
    {
        $this->receive('david', $this->header());
        $id = $this->team()->getId();
        foreach (['claim' => ['action' => 'take'], 'notes' => ['text' => 'x'], 'composing' => ['active' => true]] as $path => $body) {
            self::assertSame(404, $this->call('POST', "/api/suivis/$id/$path", $this->tok('outsider'), $body)[0], $path);
        }
        self::assertSame(404, $this->call('POST', "/api/suivis/$id/close", $this->tok('outsider'))[0]);
        self::assertSame(204, $this->call('POST', "/api/suivis/$id/close", $this->tok('lea'))[0], 'tout membre de l’alias peut clore (§9)');
    }

    public function testInternalNotesAreForMembersOnly(): void
    {
        $this->receive('david', $this->header());
        $id = $this->team()->getId();
        self::assertSame(201, $this->call('POST', "/api/suivis/$id/notes", $this->tok('claire'), ['text' => 'Je rappelle la candidate.'])[0]);
        self::assertSame(422, $this->call('POST', "/api/suivis/$id/notes", $this->tok('claire'), ['text' => '  '])[0]);
        self::assertSame(422, $this->call('POST', "/api/suivis/$id/notes", $this->tok('claire'), ['text' => str_repeat('x', 2001)])[0]);
        [, $s] = $this->call('GET', "/api/suivis/$id", $this->tok('marc'));
        self::assertSame(['Claire'], array_column($s['notes'], 'author'));
        self::assertSame('Je rappelle la candidate.', $s['notes'][0]['text']);
        self::assertSame(404, $this->call('GET', "/api/suivis/$id", $this->tok('outsider'))[0]);
        $this->call('POST', '/api/suivis', $this->tok('david'), ['subject' => 'Perso', 'contact' => 'x', 'direction' => 'waiting', 'dueAt' => '2026-12-01']);
        self::assertSame([], $this->call('GET', '/api/suivis', $this->tok('david'))[1][1]['notes'] ?? [], 'pas de notes sur une demande personnelle');
    }

    public function testComposingIndicatorWarnsOthersAndExpires(): void
    {
        $this->receive('david', $this->header());
        $id = $this->team()->getId();
        self::assertSame(['David', null], array_values($this->call('POST', "/api/suivis/$id/composing", $this->tok('david'), ['active' => true])[1]));
        [, $r] = $this->call('POST', "/api/suivis/$id/composing", $this->tok('claire'), ['active' => true]);
        self::assertSame(['composing' => 'David', 'other' => 'David'], $r, 'Claire est prévenue que David rédige, et ne le remplace pas');
        $this->call('POST', "/api/suivis/$id/composing", $this->tok('david'), ['active' => false]);
        self::assertNull($this->call('POST', "/api/suivis/$id/composing", $this->tok('claire'), ['active' => false])[1]['composing']);

        $s = $this->em->find(Suivi::class, $id);
        $s->setComposing($this->em->getReference(User::class, $this->users['david']->getId()), new \DateTimeImmutable('-5 minutes'));
        $this->em->flush();
        self::assertNull($this->em->find(Suivi::class, $id)->getComposingName(), 'l’indicateur expire seul');
    }

    public function testReplyFromTheAliasIsJournaledAndOthersAreNotified(): void
    {
        $this->receive('david', $this->header());
        $mail = $this->em->getRepository(Mail::class)->findOneBy([]);
        $smtp = static::getContainer()->get(FakeOutgoingMailer::class);
        [$st] = $this->call('POST', '/api/mails/send', $this->tok('david'), ['accountId' => $this->accounts['david']->getId(), 'to' => ['camille@gmail.com'], 'subject' => 'Re: Question d’inscription', 'body' => 'Oui, ouvertes.', 'inReplyToMailId' => $mail->getId()]);
        self::assertSame(201, $st);
        self::assertSame('<inscription-1@gmail.com>', $smtp->sent[0]->getHeaders()->get('In-Reply-To')->getBodyAsString());
        self::assertSame(['received', 'sent'], array_column($this->team()->getJournal(), 'action'));
        $kinds = fn (string $n) => array_map(fn (Notification $x) => $x->getKind(), $this->em->getRepository(Notification::class)->findBy(['owner' => $this->em->getReference(User::class, $this->users[$n]->getId())]));
        self::assertSame(['memberReplied'], $kinds('claire'));
        self::assertSame([], $kinds('david'), 'pas de notification pour l’auteur');
    }

    public function testMailboxManagementRolesAndSafety(): void
    {
        $id = $this->box->getId();
        [, $mine] = $this->call('GET', '/api/shared-mailboxes', $this->tok('claire'));
        self::assertSame(['contact', 'member'], [$mine[0]['slug'], $mine[0]['role']]);
        self::assertSame([], $this->call('GET', '/api/shared-mailboxes', $this->tok('outsider'))[1]);

        self::assertSame(403, $this->call('POST', "/api/shared-mailboxes/$id/members", $this->tok('claire'), ['username' => 'outsider'])[0], 'un simple membre ne gère pas les membres');
        self::assertSame(404, $this->call('POST', "/api/shared-mailboxes/$id/members", $this->tok('outsider'), ['username' => 'outsider'])[0]);
        self::assertSame(201, $this->call('POST', "/api/shared-mailboxes/$id/members", $this->tok('david'), ['username' => 'outsider'])[0]);
        self::assertCount(1, $this->call('GET', '/api/shared-mailboxes', $this->tok('outsider'))[1]);
        self::assertSame(422, $this->call('POST', "/api/shared-mailboxes/$id/members", $this->tok('david'), ['username' => 'fantome'])[0]);
        self::assertSame(422, $this->call('POST', "/api/shared-mailboxes/$id/members", $this->tok('david'), ['username' => 'lea', 'role' => 'roi'])[0]);

        self::assertSame(409, $this->call('DELETE', "/api/shared-mailboxes/$id/members/david", $this->tok('david'))[0], 'il faut au moins un administrateur');
        self::assertSame(200, $this->call('DELETE', "/api/shared-mailboxes/$id/members/outsider", $this->tok('david'))[0]);
        self::assertSame([], $this->call('GET', '/api/shared-mailboxes', $this->tok('outsider'))[1]);

        [$st, $c] = $this->call('POST', '/api/shared-mailboxes', $this->tok('lea'), ['address' => 'rh@iut-troyes.fr', 'name' => 'RH', 'slug' => 'rh']);
        self::assertSame([201, 'admin'], [$st, $c['role']], 'le créateur est administrateur');
        self::assertSame(409, $this->call('POST', '/api/shared-mailboxes', $this->tok('lea'), ['address' => 'RH@iut-troyes.fr', 'name' => 'RH', 'slug' => 'rh2'])[0]);
        self::assertSame(422, $this->call('POST', '/api/shared-mailboxes', $this->tok('lea'), ['address' => 'pas-un-mail', 'name' => 'x', 'slug' => 'x'])[0]);
        self::assertSame(422, $this->call('POST', '/api/shared-mailboxes', $this->tok('lea'), ['address' => 'z@x.fr', 'name' => 'x', 'slug' => 'personal'])[0], 'identifiant réservé');
    }

    public function testAiOnASharedMailboxNeedsAnAdministrator(): void
    {
        $this->receive('david', $this->header());
        $mail = $this->em->getRepository(Mail::class)->findOneBy([]);
        $spy = static::getContainer()->get(SpyAiProvider::class);
        $this->call('GET', '/api/mails/'.$mail->getId().'/hints', $this->tok('david'));
        self::assertSame([], $spy->calls, '§5 : IA coupée par défaut sur une boîte partagée');

        self::assertSame(403, $this->call('PATCH', '/api/shared-mailboxes/'.$this->box->getId(), $this->tok('claire'), ['aiEnabled' => true], 'application/merge-patch+json')[0]);
        self::assertSame(200, $this->call('PATCH', '/api/shared-mailboxes/'.$this->box->getId(), $this->tok('david'), ['aiEnabled' => true], 'application/merge-patch+json')[0]);
        $this->call('GET', '/api/mails/'.$mail->getId().'/hints', $this->tok('david'));
        self::assertCount(1, $spy->calls);
    }
}
