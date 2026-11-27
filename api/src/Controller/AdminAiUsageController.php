<?php

namespace App\Controller;

use App\Entity\AiCall;
use App\Entity\User;
use App\Entity\UserSettings;
use App\Instance\InstanceConfig;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Usage de l'IA, pour l'administrateur de l'instance (§5) : des NOMBRES seulement, sur l'ensemble. Ni personne, ni mail, ni objet, ni texte :
 * l'administrateur gère l'outil, jamais le contenu. La requête ne lit d'ailleurs pas les colonnes de contenu du journal.
 */
final class AdminAiUsageController extends AbstractController
{
    private const PERIODS = [7, 30, 90];

    public function __construct(private readonly EntityManagerInterface $em, private readonly InstanceConfig $instance)
    {
    }

    #[Route('/api/admin/ai-usage', methods: ['GET'])]
    public function __invoke(Request $r): JsonResponse
    {
        $days = (int) $r->query->get('days', 30);
        $days = \in_array($days, self::PERIODS, true) ? $days : 30;
        $since = new \DateTimeImmutable("-$days days");

        $rows = $this->em->createQuery('SELECT a.purpose AS purpose, a.provider AS provider, a.byModel AS byModel, COUNT(a.id) AS n FROM '.AiCall::class.' a WHERE a.at >= :s GROUP BY a.purpose, a.provider, a.byModel')
            ->setParameter('s', $since, Types::DATETIME_IMMUTABLE)->getArrayResult();

        $empty = ['total' => 0, 'model' => 0, 'rules' => 0, 'fallback' => 0, 'unknown' => 0];
        $totals = $empty;
        $features = [];
        $services = [];
        foreach ($rows as $row) {
            $n = (int) $row['n'];
            // Un modèle distant (choisi par la personne ou réglé par l'établissement) : « kind » suit les deux-points.
            $external = str_starts_with($row['provider'], 'external:') || str_starts_with($row['provider'], 'institution:');
            // Modèle ; règles locales choisies ; externe retombé sur les règles ; externe d'avant qu'on le note.
            $how = $row['byModel'] === true ? 'model' : (!$external ? 'rules' : ($row['byModel'] === false ? 'fallback' : 'unknown'));
            $features[$row['purpose']] ??= $empty;
            foreach (['total', $how] as $key) {
                $totals[$key] += $n;
                $features[$row['purpose']][$key] += $n;
            }
            if ($external) {
                $kind = substr((string) strrchr($row['provider'], ':'), 1);
                $services[$kind] = ($services[$kind] ?? 0) + $n;
            }
        }

        $activeUsers = (int) $this->em->createQuery('SELECT COUNT(DISTINCT IDENTITY(a.owner)) FROM '.AiCall::class.' a WHERE a.at >= :s')
            ->setParameter('s', $since, Types::DATETIME_IMMUTABLE)->getSingleScalarResult();

        $users = $this->em->getRepository(User::class)->count([]);
        $modes = ['university' => 0, 'external' => 0, 'off' => 0];
        $withSettings = 0;
        foreach ($this->em->getRepository(UserSettings::class)->findAll() as $s) {
            $mode = (string) $s->all()['aiMode'];
            $modes[$mode] = ($modes[$mode] ?? 0) + 1;
            ++$withSettings;
        }
        // Sans réglage enregistré : le mode par défaut.
        $modes['university'] += max(0, $users - $withSettings);

        return new JsonResponse([
            'days' => $days, 'externalAllowed' => $this->instance->aiExternalAllowed(), 'users' => $users, 'activeUsers' => $activeUsers, 'modes' => $modes,
            'totals' => $totals,
            'features' => array_map(static fn (string $k, array $v) => ['feature' => $k] + $v, array_keys($features), array_values($features)),
            'services' => array_map(static fn (string $k, int $v) => ['service' => $k, 'total' => $v], array_keys($services), array_values($services)),
        ]);
    }
}
