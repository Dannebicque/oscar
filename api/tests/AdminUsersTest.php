<?php

namespace App\Tests;

use App\Entity\AuditEntry;
use App\Entity\MailAccount;
use App\Entity\Mail;
use App\Entity\Task;
use App\Entity\User;
use App\Instance\Heartbeat;
use App\Mail\MailHeader;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Mailer\MailerInterface;

/** Comptes de l'instance, santé et audit : l'administrateur gère l'outil, jamais le contenu des utilisateurs. */
final class AdminUsersTest extends ApiTestCase
{
    private User $admin;
    private string $adminToken;
    private User $claire;
    private string $claireId = '';
    private string $adminId = '';
    private string $claireToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user('ada');
        $this->admin->setInstanceAdmin(true)->setEmail('ada@exemple.fr');
        $this->claire = $this->user('claire');
        $this->claire->setEmail('claire@exemple.fr');
        $this->em->flush();
        $this->claireId = $this->claire->getId();
        $this->adminId = $this->admin->getId();
        $this->adminToken = $this->tokenFor($this->admin);
        $this->claireToken = $this->tokenFor($this->claire);
    }

    private function audit(): array
    {
        return array_map(fn (AuditEntry $e) => $e->toArray()['action'], $this->em->getRepository(AuditEntry::class)->findBy([], ['at' => 'ASC']));
    }

    public function testOnlyTheAdministratorSeesTheUsersAndNeverTheirContent(): void
    {
        self::assertSame(403, $this->call('GET', '/api/admin/users', $this->claireToken)[0]);
        self::assertSame(401, $this->call('GET', '/api/admin/users')[0]);
        $claire = $this->em->find(User::class, $this->claireId);
        $acc = (new MailAccount())->setOwner($claire)->setLabel('X')->setAddress('c@x.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('c');
        $this->em->persist($acc);
        $this->em->persist(Mail::fromHeader($acc, new MailHeader('<s@x>', null, [], 'Marc', 'm@x.fr', [], 'Sujet très privé', new \DateTimeImmutable(), false, 1, 'INBOX')));
        $this->em->persist((new Task())->setOwner($claire)->setTitle('Tâche très privée'));
        $this->em->flush();

        [$st, $list] = $this->call('GET', '/api/admin/users', $this->adminToken);
        self::assertSame(200, $st);
        $json = json_encode($list);
        self::assertStringNotContainsString('très privé', $json);
        $c = array_values(array_filter($list, fn ($u) => $u['username'] === 'claire'))[0];
        self::assertSame([false, false, 1], [$c['admin'], $c['disabled'], $c['mailAccounts']]);
        self::assertSame(['id', 'name', 'email', 'username', 'admin', 'disabled', 'local', 'createdAt', 'lastLoginAt', 'mailAccounts'], array_keys($c), 'aucun compteur de mails, tâches ou agendas');
        self::assertSame(404, $this->call('GET', '/api/admin/users/'.$this->claireId.'/mails', $this->adminToken)[0], 'aucune porte vers le contenu');
    }

    public function testAnInvitationCreatesALocalAccountAndSendsTheLinkOnlyByEmail(): void
    {
        [$st, $r] = $this->call('POST', '/api/admin/users', $this->adminToken, ['name' => 'Nouvelle Personne', 'email' => 'Nouvelle@Exemple.fr']);
        self::assertSame(201, $st);
        self::assertArrayNotHasKey('link', $r);
        self::assertArrayNotHasKey('token', $r);
        $u = $this->em->getRepository(User::class)->findOneBy(['email' => 'nouvelle@exemple.fr']);
        self::assertNotNull($u);
        self::assertNull($u->getPassword(), 'pas de mot de passe : l’invité le choisit lui-même');
        self::assertSame(409, $this->call('POST', '/api/admin/users', $this->adminToken, ['name' => 'Doublon', 'email' => 'nouvelle@exemple.fr'])[0]);
        self::assertSame(422, $this->call('POST', '/api/admin/users', $this->adminToken, ['name' => '', 'email' => 'pas-une-adresse'])[0]);
        self::assertSame(403, $this->call('POST', '/api/admin/users', $this->claireToken, ['name' => 'X', 'email' => 'x@exemple.fr'])[0]);
        self::assertContains('user.invited', $this->audit());
    }

    public function testDisablingLogsTheUserOutEverywhereAndForbidsNewLogins(): void
    {
        $this->call('PUT', '/api/admin/config', $this->adminToken, ['registration' => 'open']);
        $this->call('POST', '/api/auth/register', null, ['name' => 'Léa', 'email' => 'lea@exemple.fr', 'password' => 'mot-de-passe-long-1']);
        $lea = $this->em->getRepository(User::class)->findOneBy(['email' => 'lea@exemple.fr']);
        $token = $this->call('POST', '/api/auth/login', null, ['email' => 'lea@exemple.fr', 'password' => 'mot-de-passe-long-1'])[1]['token'];
        self::assertSame(200, $this->call('GET', '/api/me', $token)[0]);

        self::assertSame(200, $this->call('POST', '/api/admin/users/'.$lea->getId().'/disable', $this->adminToken)[0]);
        self::assertSame(401, $this->call('GET', '/api/me', $token)[0], 'sa session est coupée tout de suite');
        self::assertSame(403, $this->call('POST', '/api/auth/login', null, ['email' => 'lea@exemple.fr', 'password' => 'mot-de-passe-long-1'])[0], 'et elle ne peut plus se reconnecter');

        self::assertSame(200, $this->call('POST', '/api/admin/users/'.$lea->getId().'/enable', $this->adminToken)[0]);
        self::assertSame(200, $this->call('POST', '/api/auth/login', null, ['email' => 'lea@exemple.fr', 'password' => 'mot-de-passe-long-1'])[0], 'réactivé : rien n’a été perdu');
        self::assertSame(['user.disabled', 'user.enabled'], array_values(array_diff($this->audit(), ['config.changed'])));
    }

    public function testTheLastAdministratorAndYourselfAreProtected(): void
    {
        $id = $this->adminId;
        self::assertSame(409, $this->call('POST', '/api/admin/users/'.$id.'/disable', $this->adminToken)[0]);
        self::assertSame(409, $this->call('DELETE', '/api/admin/users/'.$id, $this->adminToken, ['confirm' => 'ada@exemple.fr'])[0]);
        self::assertSame(409, $this->call('POST', '/api/admin/users/'.$id.'/admin', $this->adminToken, ['admin' => false])[0], 'le seul administrateur ne peut pas être rétrogradé');

        self::assertSame(200, $this->call('POST', '/api/admin/users/'.$this->claireId.'/admin', $this->adminToken, ['admin' => true])[0]);
        self::assertSame(200, $this->call('GET', '/api/admin/config', $this->claireToken)[0], 'nouvelle administratrice');
        self::assertSame(200, $this->call('POST', '/api/admin/users/'.$id.'/admin', $this->claireToken, ['admin' => false])[0], 'avec deux administrateurs, on peut en retirer un');
        self::assertSame(422, $this->call('POST', '/api/admin/users/'.$id.'/admin', $this->claireToken, ['admin' => 'oui'])[0]);
    }

    public function testDeletionNeedsTheAddressAndRemovesTheAccountAndItsData(): void
    {
        $claire = $this->em->find(User::class, $this->claireId);
        $acc = (new MailAccount())->setOwner($claire)->setLabel('X')->setAddress('c@x.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('c');
        $this->em->persist($acc);
        $this->em->persist((new Task())->setOwner($claire)->setTitle('À effacer'));
        $this->em->flush();
        $id = $this->claireId;

        [$st, $e] = $this->call('DELETE', '/api/admin/users/'.$id, $this->adminToken, ['confirm' => 'mauvaise@adresse.fr']);
        self::assertSame([422, 'confirm'], [$st, $e['field']]);
        self::assertSame(1, $this->em->getRepository(Task::class)->count([]));
        self::assertSame(204, $this->call('DELETE', '/api/admin/users/'.$id, $this->adminToken, ['confirm' => 'CLAIRE@exemple.fr'])[0]);
        $this->em->clear();
        self::assertNull($this->em->find(User::class, $id));
        // Les données suivent par cascade au niveau de la base (PostgreSQL) : SQLite ne l'applique pas, vérifié à part contre PostgreSQL.
        self::assertSame(401, $this->call('GET', '/api/me', $this->claireToken)[0]);
        self::assertSame(404, $this->call('DELETE', '/api/admin/users/'.$id, $this->adminToken, ['confirm' => 'x'])[0]);
        self::assertSame(['user.deleted'], $this->audit());
    }

    public function testResetLinkGoesByEmailToLocalAccountsOnly(): void
    {
        $local = $this->user('local');
        $local->setEmail('local@exemple.fr')->setPasswordHash('x');
        $this->em->flush();
        [$st, $r] = $this->call('POST', '/api/admin/users/'.$local->getId().'/reset-link', $this->adminToken);
        self::assertSame(200, $st);
        self::assertSame(['emailSent'], array_keys($r), 'le lien n’est jamais renvoyé à l’administrateur');
        self::assertSame(409, $this->call('POST', '/api/admin/users/'.$this->claireId.'/reset-link', $this->adminToken)[0], 'compte sans mot de passe (établissement)');
        self::assertSame(['user.resetLink'], $this->audit());
    }

    public function testHealthReportsServicesAndCountsOnly(): void
    {
        static::getContainer()->get(Heartbeat::class)->beat();
        [$st, $h] = $this->call('GET', '/api/admin/health', $this->adminToken);
        self::assertSame(200, $st);
        $by = array_column($h['checks'], 'status', 'id');
        self::assertSame(['ok', 'ok'], [$by['database'], $by['worker']]);
        self::assertSame(2, $h['counts']['users']);
        self::assertSame(1, $h['counts']['admins']);
        self::assertSame(403, $this->call('GET', '/api/admin/health', $this->claireToken)[0]);
    }

    public function testAMissingHeartbeatIsAnErrorAndTheAuditIsReadableByAdminsOnly(): void
    {
        [, $h] = $this->call('GET', '/api/admin/health', $this->adminToken);
        self::assertSame('error', array_column($h['checks'], 'status', 'id')['worker'], 'aucun battement : les tâches de fond ne tournent pas');
        $this->call('PUT', '/api/admin/config', $this->adminToken, ['instanceName' => 'Cabinet']);
        [$st, $a] = $this->call('GET', '/api/admin/audit', $this->adminToken);
        self::assertSame([200, 'config.changed', ['keys' => ['instanceName']]], [$st, $a[0]['action'], $a[0]['details']]);
        self::assertStringContainsString('ada@exemple.fr', $a[0]['actor']);
        self::assertStringNotContainsString('Cabinet', json_encode($a), 'les valeurs ne sont pas gardées, seulement les noms de réglages');
        self::assertSame(403, $this->call('GET', '/api/admin/audit', $this->claireToken)[0]);
    }
}
