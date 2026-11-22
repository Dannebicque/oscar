<?php

namespace App\Controller;

use App\Entity\AuditEntry;
use App\Entity\MailAccount;
use App\Entity\ScheduledMail;
use App\Entity\User;
use App\Instance\Heartbeat;
use App\Instance\SetupToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Santé et audit de l'instance pour l'administrateur. Ce sont des compteurs et des états de service, jamais du contenu d'utilisateur :
 * on dit « 2 comptes de messagerie ne se synchronisent plus », pas lesquels des mails ils contiennent.
 */
final class AdminHealthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Heartbeat $heartbeat,
        private readonly SetupToken $setupToken,
        #[Autowire('%kernel.environment%')] private readonly string $env,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire('%env(FRONT_URL)%')] private readonly string $frontUrl,
        #[Autowire('%env(MAILER_DSN)%')] private readonly string $mailerDsn,
        #[Autowire('%env(default::APP_VERSION)%')] private readonly ?string $version,
    ) {
    }

    #[Route('/api/admin/health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $checks = [];
        $add = static function (string $id, string $status, array $data = []) use (&$checks): void {
            $checks[] = ['id' => $id, 'status' => $status] + ['data' => $data];
        };

        $t = microtime(true);
        try {
            $this->em->getConnection()->executeQuery('SELECT 1')->fetchOne();
            $add('database', 'ok', ['ms' => (int) round((microtime(true) - $t) * 1000)]);
        } catch (\Throwable) {
            $add('database', 'error');
        }

        $age = $this->heartbeat->age();
        $add('worker', $age === null ? 'error' : ($age <= 180 ? 'ok' : 'error'), ['ageSeconds' => $age]);

        $accounts = $this->em->getRepository(MailAccount::class)->findAll();
        $stale = 0;
        $failing = 0;
        $limit = new \DateTimeImmutable('-15 minutes');
        foreach ($accounts as $a) {
            $failing += $a->getFailureCount() >= 3 ? 1 : 0;
            $stale += $a->getLastSyncAt() !== null && $a->getLastSyncAt() < $limit ? 1 : 0;
        }
        $add('sync', $failing > 0 ? 'warn' : 'ok', ['accounts' => \count($accounts), 'failing' => $failing, 'stale' => $stale]);

        $failed = $this->em->getRepository(ScheduledMail::class)->count(['status' => ScheduledMail::FAILED]);
        $add('scheduled', $failed > 0 ? 'warn' : 'ok', ['failed' => $failed, 'pending' => $this->em->getRepository(ScheduledMail::class)->count(['status' => ScheduledMail::PENDING])]);

        $free = @disk_free_space($this->projectDir);
        $total = @disk_total_space($this->projectDir);
        if ($free !== false && $total) {
            $pct = (int) round($free / $total * 100);
            $add('disk', $pct < 10 ? 'error' : ($pct < 20 ? 'warn' : 'ok'), ['freePercent' => $pct, 'freeGb' => round($free / 1073741824, 1)]);
        }

        $add('mailer', str_starts_with($this->mailerDsn, 'null:') ? 'warn' : 'ok');
        $add('https', str_starts_with($this->frontUrl, 'https://') ? 'ok' : 'warn');
        $add('environment', $this->env === 'prod' ? 'ok' : 'warn', ['env' => $this->env]);
        $leftover = $this->setupToken->current() !== null && $this->em->getRepository(User::class)->count(['instanceAdmin' => true]) > 0;
        $add('setupCode', $leftover ? 'warn' : 'ok');

        $users = $this->em->getRepository(User::class);
        $since = new \DateTimeImmutable('-30 days');

        return new JsonResponse([
            'version' => $this->version ?: 'dev', 'php' => \PHP_VERSION, 'checks' => $checks,
            'counts' => [
                'users' => $users->count([]), 'disabled' => $users->count(['disabled' => true]), 'admins' => $users->count(['instanceAdmin' => true]), 'mailAccounts' => \count($accounts),
                'activeLast30Days' => (int) $this->em->createQuery('SELECT COUNT(DISTINCT IDENTITY(t.user)) FROM App\Entity\ApiToken t WHERE t.createdAt >= :s')->setParameter('s', $since)->getSingleScalarResult(),
            ],
        ]);
    }

    #[Route('/api/admin/audit', methods: ['GET'])]
    public function audit(Request $r): JsonResponse
    {
        $limit = min(200, max(1, (int) $r->query->get('limit', 100)));
        $rows = $this->em->getRepository(AuditEntry::class)->findBy([], ['at' => 'DESC'], $limit);

        return new JsonResponse(array_map(static fn (AuditEntry $e) => $e->toArray(), $rows));
    }
}
