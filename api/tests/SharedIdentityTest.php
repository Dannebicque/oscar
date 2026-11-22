<?php

namespace App\Tests;

use App\Entity\MailAccount;
use App\Entity\SharedMailbox;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\FakeOutgoingMailer;

/** Identité d'envoi d'une boîte partagée : « De : contact@ », nom et signature d'équipe réglés par un administrateur. */
final class SharedIdentityTest extends ApiTestCase
{
    private SharedMailbox $box;
    private string $admin;
    private string $member;
    private string $outsider;
    private MailAccount $memberAccount;
    private MailAccount $outsiderAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $david = $this->user('david');
        $claire = $this->user('claire');
        $marc = $this->user('marc');
        $this->box = new SharedMailbox('contact@iut-troyes.fr', 'Contact', 'contact');
        $this->box->addMember($david, 'admin');
        $this->box->addMember($claire, 'member');
        $this->em->persist($this->box);
        $this->memberAccount = (new MailAccount())->setOwner($claire)->setLabel('IUT')->setAddress('claire@iut-troyes.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('claire');
        $this->outsiderAccount = (new MailAccount())->setOwner($marc)->setLabel('IUT')->setAddress('marc@iut-troyes.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('marc');
        $this->em->persist($this->memberAccount);
        $this->em->persist($this->outsiderAccount);
        $this->em->flush();
        $this->admin = $this->tokenFor($david);
        $this->member = $this->tokenFor($claire);
        $this->outsider = $this->tokenFor($marc);
    }

    public function testOnlyAnAdministratorSetsTheTeamNameAndSignature(): void
    {
        $id = $this->box->getId();
        [$st, $b] = $this->call('PATCH', '/api/shared-mailboxes/'.$id, $this->admin, ['senderName' => 'Équipe Contact IUT', 'signature' => "-- \nL'équipe contact"]);
        self::assertSame([200, 'Équipe Contact IUT', 'shared:'.$id], [$st, $b['senderName'], $b['identityId']]);
        self::assertSame(403, $this->call('PATCH', '/api/shared-mailboxes/'.$id, $this->member, ['senderName' => 'Moi'])[0]);
        self::assertSame(404, $this->call('PATCH', '/api/shared-mailboxes/'.$id, $this->outsider, ['senderName' => 'Moi'])[0]);
        foreach ([['senderName' => 'A<b>'], ['senderName' => str_repeat('x', 101)], ['signature' => str_repeat('x', 2001)], ['senderName' => 5]] as $bad) {
            self::assertSame(422, $this->call('PATCH', '/api/shared-mailboxes/'.$id, $this->admin, $bad)[0], json_encode($bad));
        }
        [, $list] = $this->call('GET', '/api/shared-mailboxes', $this->member);
        self::assertSame(["-- \nL'équipe contact", 'Équipe Contact IUT'], [$list[0]['signature'], $list[0]['senderName']], 'les membres voient le nom et la signature');
        self::assertSame(200, $this->call('PATCH', '/api/shared-mailboxes/'.$id, $this->admin, ['senderName' => '', 'signature' => null])[0], 'vider revient au nom de la boîte');
    }

    public function testAMemberSendsFromTheSharedAddressAndAnOutsiderCannot(): void
    {
        $smtp = static::getContainer()->get(FakeOutgoingMailer::class);
        $this->call('PATCH', '/api/shared-mailboxes/'.$this->box->getId(), $this->admin, ['senderName' => 'Équipe Contact IUT']);
        $base = ['to' => ['lycee@x.fr'], 'subject' => 'Votre demande', 'body' => 'Bonjour'];

        [$st] = $this->call('POST', '/api/mails/send', $this->member, $base + ['accountId' => $this->memberAccount->getId(), 'identityId' => 'shared:'.$this->box->getId()]);
        self::assertSame(201, $st);
        $from = end($smtp->sent)->getFrom()[0];
        self::assertSame(['contact@iut-troyes.fr', 'Équipe Contact IUT'], [$from->getAddress(), $from->getName()]);

        [$st] = $this->call('POST', '/api/mails/send', $this->outsider, $base + ['accountId' => $this->outsiderAccount->getId(), 'identityId' => 'shared:'.$this->box->getId()]);
        self::assertSame(422, $st, 'un non-membre ne peut pas écrire De : contact@');
        self::assertSame(422, $this->call('POST', '/api/mails/send', $this->member, $base + ['accountId' => $this->memberAccount->getId(), 'identityId' => 'shared:0190a000-0000-7000-8000-000000000000'])[0]);

        [$st] = $this->call('POST', '/api/mails/draft', $this->member, $base + ['accountId' => $this->memberAccount->getId(), 'identityId' => 'shared:'.$this->box->getId()]);
        self::assertSame(201, $st, 'un brouillon peut aussi partir de l’identité de la boîte');
        self::assertSame(422, $this->call('POST', '/api/mails/draft', $this->outsider, $base + ['accountId' => $this->outsiderAccount->getId(), 'identityId' => 'shared:'.$this->box->getId()])[0]);
    }
}
