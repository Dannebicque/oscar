<?php

namespace App\Tests;

use App\Entity\AiCall;
use App\Entity\CalendarAccount;
use App\Entity\CalendarEvent;
use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Entity\Suivi;
use App\Entity\UserSettings;
use App\Mail\MailHeader;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\SpyAiProvider;

final class AiApiTest extends ApiTestCase
{
    private string $token;
    private MailAccount $account;
    private Mail $mail;

    protected function setUp(): void
    {
        parent::setUp();
        $david = $this->user('david');
        $this->account = (new MailAccount())->setOwner($david)->setLabel('IUT')->setAddress('d@x.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('d');
        $this->em->persist($this->account);
        $this->mail = Mail::fromHeader($this->account, new MailHeader('<m@x>', null, [], 'Sophie', 's@x.fr', [], 'Planning', new \DateTimeImmutable('2026-11-19 09:00:00 Europe/Paris'), false, 1));
        $this->em->persist($this->mail);
        $this->em->flush();
        $this->token = $this->tokenFor($david);
    }

    private function setting(array $changes): void
    {
        $this->call('PUT', '/api/settings', $this->token, $changes);
    }

    public function testHintsAreProducedFromTheMailBodyAndJournaled(): void
    {
        // FakeReaderFactory renvoie « Corps simulé » : on vérifie le circuit complet, pas l'extraction (testée à part).
        [$st, $h] = $this->call('GET', '/api/mails/'.$this->mail->getId().'/hints', $this->token);
        self::assertSame(200, $st);
        self::assertSame(['availability', 'event', 'task', 'suggestFollow', 'summary'], array_keys($h));
        $calls = $this->em->getRepository(AiCall::class)->findAll();
        self::assertCount(1, $calls);
        self::assertSame('mail-hints', $calls[0]->getPurpose());
        self::assertStringNotContainsString('Corps simulé', $calls[0]->getInputSummary(), 'le journal ne contient jamais le contenu du mail');
        [, $log] = $this->call('GET', '/api/ai-calls', $this->token);
        self::assertSame('spy', $log[0]['provider']);
    }

    public function testReplyDraftIsPreparedNeverSentAndJournaledWithoutContent(): void
    {
        [$st, $d] = $this->call('POST', '/api/mails/'.$this->mail->getId().'/reply-draft', $this->token, ['tone' => 'short', 'slots' => ['Lun. 30 nov., 10:00 – 11:00']]);
        self::assertSame(200, $st);
        self::assertStringContainsString('- Lun. 30 nov., 10:00 – 11:00', $d['body']);
        self::assertTrue($d['ai']);
        $call = array_values(array_filter($this->em->getRepository(AiCall::class)->findAll(), static fn ($c) => $c->getPurpose() === 'reply-draft'))[0];
        self::assertStringNotContainsString('Lun.', $call->getInputSummary());
        $this->setting(['aiMode' => 'off']);
        [$st] = $this->call('POST', '/api/mails/'.$this->mail->getId().'/reply-draft', $this->token, []);
        self::assertSame(403, $st, 'IA coupée : aucun brouillon');
    }

    public function testNothingIsReadWhenAiIsOffExternalOrForbiddenOnTheMailbox(): void
    {
        $spy = static::getContainer()->get(SpyAiProvider::class);
        foreach (['off', 'external'] as $mode) {
            $this->setting(['aiMode' => $mode]);
            [$st, $h] = $this->call('GET', '/api/mails/'.$this->mail->getId().'/hints', $this->token);
            self::assertSame(200, $st);
            self::assertNull($h['task']);
        }
        $this->setting(['aiMode' => 'university']);
        $acc = $this->em->find(MailAccount::class, $this->account->getId());
        $acc->setAiAllowed(false);
        $this->em->flush();
        $this->call('GET', '/api/mails/'.$this->mail->getId().'/hints', $this->token);
        self::assertSame([], $spy->calls, 'le fournisseur n’a rien reçu');
        self::assertSame(0, $this->em->getRepository(AiCall::class)->count([]));
    }

    public function testSuggestionSwitchesInSettingsAreHonoured(): void
    {
        $this->setting(['aiSuggestTasks' => false, 'aiSuggestFollow' => false]);
        $spy = static::getContainer()->get(SpyAiProvider::class);
        [, $h] = $this->call('GET', '/api/mails/'.$this->mail->getId().'/hints', $this->token);
        self::assertSame(['availability' => null, 'event' => null, 'task' => null, 'suggestFollow' => false, 'summary' => null], $h);
        self::assertSame([], $spy->calls);
    }

    public function testProviderOnlyReceivesBusySlotsNeverTitlesOrPersonalCalendar(): void
    {
        $david = $this->em->getReference(\App\Entity\User::class, $this->account->getOwner()->getId());
        $work = (new CalendarAccount())->setOwner($david)->setLabel('IUT')->setUrl('https://x/');
        $perso = (new CalendarAccount())->setOwner($david)->setLabel('Perso')->setUrl('https://y/')->setPersonal(true);
        $this->em->persist($work);
        $this->em->persist($perso);
        foreach ([[$work, 'Entretien confidentiel RH', 'Salle secrète'], [$perso, 'Rendez-vous médecin', 'Cabinet']] as [$c, $title, $place]) {
            $this->em->persist((new CalendarEvent())->setOwner($david)->setCalendar($c)->setTitle($title)->setLocation($place)->setStart(new \DateTimeImmutable('2026-11-25 10:00'))->setEnd(new \DateTimeImmutable('2026-11-25 11:00')));
        }
        $this->em->flush();
        $spy = static::getContainer()->get(SpyAiProvider::class);
        $this->call('GET', '/api/mails/'.$this->mail->getId().'/hints', $this->token);

        self::assertCount(1, $spy->calls[0]['busy'], 'l’agenda perso est exclu par défaut');
        self::assertSame(['start', 'end'], array_keys($spy->calls[0]['busy'][0]));
        self::assertStringNotContainsString('confidentiel', json_encode($spy->calls));
        self::assertStringNotContainsString('secrète', json_encode($spy->calls));

        $this->setting(['includePersonalCalendar' => true]);
        $this->call('GET', '/api/mails/'.$this->mail->getId().'/hints', $this->token);
        self::assertCount(2, $spy->calls[1]['busy'], 'inclus seulement sur réglage');
        self::assertStringNotContainsString('médecin', json_encode($spy->calls));
    }

    public function testRelanceDraftNeedsTheSettingAndIsPrivate(): void
    {
        $david = $this->em->getReference(\App\Entity\User::class, $this->account->getOwner()->getId());
        $s = (new Suivi())->setOwner($david)->setSubject('Devis des affiches')->setContact('Marc Dubois');
        $this->em->persist($s);
        $this->em->flush();
        [$st, $r] = $this->call('POST', '/api/suivis/'.$s->getId().'/relance-draft', $this->token, []);
        self::assertSame(200, $st);
        self::assertStringContainsString('Bonjour Marc', $r['body']);
        self::assertTrue($r['ai']);

        $this->setting(['aiDraftRelance' => false]);
        self::assertSame(403, $this->call('POST', '/api/suivis/'.$s->getId().'/relance-draft', $this->token, [])[0]);
        $other = $this->tokenFor($this->user('claire'));
        self::assertSame(404, $this->call('POST', '/api/suivis/'.$s->getId().'/relance-draft', $other, [])[0]);
        self::assertSame(404, $this->call('GET', '/api/mails/'.$this->mail->getId().'/hints', $other)[0]);
        self::assertSame([], $this->call('GET', '/api/ai-calls', $other)[1]);
    }

    public function testComposeHintsSuggestsATaskFromWhatTheUserWritesAndRespectsTheSettings(): void
    {
        $body = 'Bonjour Sophie, peux-tu confirmer le nombre de participants avant mardi ? Merci.';
        [$st, $r] = $this->call('POST', '/api/ai/compose-hints', $this->token, ['subject' => 'Planning', 'body' => $body]);
        self::assertSame(200, $st);
        self::assertStringContainsString('participants', $r['task']['title']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $r['task']['dueAt']);
        [, $short] = $this->call('POST', '/api/ai/compose-hints', $this->token, ['subject' => 'x', 'body' => 'Salut']);
        self::assertNull($short['task'], 'texte trop court : rien');
        $this->setting(['aiSuggestTasks' => false]);
        [, $off] = $this->call('POST', '/api/ai/compose-hints', $this->token, ['subject' => 'Planning', 'body' => $body]);
        self::assertNull($off['task'], 'suggestions de tâches coupées dans Réglages');
        $this->setting(['aiSuggestTasks' => true, 'aiMode' => 'off']);
        [, $none] = $this->call('POST', '/api/ai/compose-hints', $this->token, ['subject' => 'Planning', 'body' => $body]);
        self::assertNull($none['task'], 'IA coupée : aucune lecture');
    }
}
