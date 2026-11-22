<?php

namespace App\Controller;

use App\Entity\User;
use App\Instance\InstanceConfig;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Configuration globale de l'instance. La partie publique ne donne que ce dont l'écran de connexion a besoin ;
 * la partie /api/admin est réservée à l'administrateur de l'instance et ne touche jamais aux mails ni aux agendas des utilisateurs.
 */
final class InstanceController extends AbstractController
{
    public function __construct(private readonly InstanceConfig $config, private readonly EntityManagerInterface $em, private readonly \App\Instance\AuditLog $audit)
    {
    }

    /** Public : nom affiché, inscriptions, liens légaux, et si l'assistant d'installation doit s'ouvrir. */
    #[Route('/api/instance', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $c = $this->config->all();

        return new JsonResponse([
            'name' => $c['instanceName'], 'registration' => $c['registration'] === 'open', 'supportEmail' => $c['supportEmail'], 'legalUrl' => $c['legalUrl'],
            'privacyUrl' => $c['privacyUrl'], 'aiExternalAllowed' => $c['aiExternalAllowed'], 'defaultAttachmentMb' => $c['defaultAttachmentMb'],
            'setupNeeded' => $this->setupNeeded(),
        ]);
    }

    #[Route('/api/admin/config', methods: ['GET'])]
    public function adminShow(): JsonResponse
    {
        return new JsonResponse($this->config->all());
    }

    #[Route('/api/admin/config', methods: ['PUT', 'PATCH'])]
    public function adminUpdate(Request $r, #[\Symfony\Component\Security\Http\Attribute\CurrentUser] User $admin): JsonResponse
    {
        $before = $this->config->all();
        $errors = $this->config->apply($r->toArray());
        if ($errors !== []) {
            return new JsonResponse(['title' => 'Certains réglages ne sont pas valides.', 'fields' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $after = $this->config->all();
        $changed = array_keys(array_filter($after, static fn ($v, $k) => ($before[$k] ?? null) !== $v, \ARRAY_FILTER_USE_BOTH));
        if ($changed !== []) {
            // Les noms des réglages changés, pas leurs valeurs : le journal n'a pas à garder d'adresses ni de textes.
            $this->audit->record($admin, 'config.changed', null, ['keys' => $changed]);
        }

        return new JsonResponse($after);
    }

    private function setupNeeded(): bool
    {
        return $this->em->getRepository(User::class)->count(['instanceAdmin' => true]) === 0;
    }
}
