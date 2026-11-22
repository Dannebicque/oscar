<?php

namespace App\Controller;

use App\Entity\ApiToken;
use App\Entity\MailAccount;
use App\Entity\User;
use App\Instance\AuditLog;
use App\Instance\InstanceConfig;
use App\Security\PasswordLink;
use App\Security\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Comptes de l'instance, pour l'administrateur : lister, inviter, désactiver, supprimer, donner ou retirer le rôle d'administrateur.
 * Ce qui n'existe PAS ici, volontairement : lire un mail, un agenda ou une tâche, se connecter « en tant que », ou recevoir un lien
 * de réinitialisation (il part par e-mail à l'utilisateur seulement). Chaque action est inscrite dans le journal d'audit.
 */
final class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TokenService $tokens,
        private readonly AuditLog $audit,
        private readonly PasswordLink $links,
        private readonly InstanceConfig $config,
    ) {
    }

    #[Route('/api/admin/users', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $last = [];
        foreach ($this->em->createQuery('SELECT IDENTITY(t.user) AS u, MAX(t.createdAt) AS at FROM '.ApiToken::class.' t GROUP BY t.user')->getArrayResult() as $r) {
            $last[$r['u']] = $r['at'];
        }
        $accounts = [];
        foreach ($this->em->createQuery('SELECT IDENTITY(a.owner) AS o, COUNT(a.id) AS n FROM '.MailAccount::class.' a GROUP BY a.owner')->getArrayResult() as $r) {
            $accounts[$r['o']] = (int) $r['n'];
        }
        $out = [];
        foreach ($this->em->getRepository(User::class)->findBy([], ['displayName' => 'ASC']) as $u) {
            // Rien du contenu : ni mails, ni agendas, ni tâches, ni même leur nombre.
            $out[] = [
                'id' => $u->getId(), 'name' => $u->getDisplayName(), 'email' => $u->getEmail(), 'username' => $u->getUsername(), 'admin' => $u->isInstanceAdmin(), 'disabled' => $u->isDisabled(),
                'local' => $u->getPassword() !== null, 'createdAt' => $u->getCreatedAt()->format(\DATE_ATOM), 'lastLoginAt' => isset($last[$u->getId()]) ? (new \DateTimeImmutable((string) $last[$u->getId()]))->format(\DATE_ATOM) : null,
                'mailAccounts' => $accounts[$u->getId()] ?? 0,
            ];
        }

        return new JsonResponse($out);
    }

    /** Inviter : crée un compte local sans mot de passe et envoie le lien pour en choisir un, par e-mail à l'invité. */
    #[Route('/api/admin/users', methods: ['POST'])]
    public function invite(Request $r, #[CurrentUser] User $admin): JsonResponse
    {
        $in = $r->toArray();
        $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
        $name = trim((string) ($in['name'] ?? ''));
        if (!filter_var($email, \FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
            return $this->invalid('email', 'Cette adresse n’a pas l’air valide.');
        }
        if ($name === '' || mb_strlen($name) > 160) {
            return $this->invalid('name', 'Indique le nom de la personne.');
        }
        if ($this->em->getRepository(User::class)->findOneBy(['username' => $email]) !== null || $this->em->getRepository(User::class)->findOneBy(['email' => $email]) !== null) {
            return $this->invalid('email', 'Un compte existe déjà avec cette adresse.', Response::HTTP_CONFLICT);
        }
        $user = new User($email, $name, $email);
        $this->em->persist($user);
        $this->em->flush();
        $sent = $this->links->send($user, 'invite', $this->config->name());
        $this->audit->record($admin, 'user.invited', $email, ['emailSent' => $sent]);

        return new JsonResponse(['id' => $user->getId(), 'emailSent' => $sent], Response::HTTP_CREATED);
    }

    #[Route('/api/admin/users/{id}/disable', methods: ['POST'])]
    public function disable(string $id, #[CurrentUser] User $admin): JsonResponse
    {
        return $this->toggle($id, $admin, true);
    }

    #[Route('/api/admin/users/{id}/enable', methods: ['POST'])]
    public function enable(string $id, #[CurrentUser] User $admin): JsonResponse
    {
        return $this->toggle($id, $admin, false);
    }

    /** Envoie à l'utilisateur (et à lui seul) un lien pour choisir un nouveau mot de passe. Comptes locaux seulement. */
    #[Route('/api/admin/users/{id}/reset-link', methods: ['POST'])]
    public function resetLink(string $id, #[CurrentUser] User $admin): JsonResponse
    {
        $u = $this->em->find(User::class, $id);
        if ($u === null) {
            return new JsonResponse(['title' => 'Compte inconnu.'], Response::HTTP_NOT_FOUND);
        }
        if ($u->getPassword() === null) {
            return new JsonResponse(['title' => 'Ce compte se connecte par l’établissement : son mot de passe se change là-bas.'], Response::HTTP_CONFLICT);
        }
        $sent = $this->links->send($u, 'reset', $this->config->name());
        $this->audit->record($admin, 'user.resetLink', $u->getEmail() ?? $u->getUsername(), ['emailSent' => $sent]);

        return new JsonResponse(['emailSent' => $sent]);
    }

    #[Route('/api/admin/users/{id}/admin', methods: ['POST'])]
    public function setAdmin(string $id, Request $r, #[CurrentUser] User $admin): JsonResponse
    {
        $u = $this->em->find(User::class, $id);
        $make = ($r->toArray()['admin'] ?? null);
        if ($u === null) {
            return new JsonResponse(['title' => 'Compte inconnu.'], Response::HTTP_NOT_FOUND);
        }
        if (!\is_bool($make)) {
            return $this->invalid('admin', 'Attendu : oui ou non.');
        }
        if (!$make && $this->lastAdmin($u)) {
            return new JsonResponse(['title' => 'C’est le seul administrateur : nomme-en un autre avant de lui retirer le rôle.'], Response::HTTP_CONFLICT);
        }
        $u->setInstanceAdmin($make);
        $this->em->flush();
        $this->audit->record($admin, 'user.admin', $u->getEmail() ?? $u->getUsername(), ['admin' => $make]);

        return new JsonResponse(['id' => $u->getId(), 'admin' => $make]);
    }

    /** Suppression définitive du compte et de ses données (RGPD) : l'administrateur recopie l'adresse pour confirmer. Les mails restent sur le serveur de messagerie. */
    #[Route('/api/admin/users/{id}', methods: ['DELETE'])]
    public function delete(string $id, Request $r, #[CurrentUser] User $admin): JsonResponse
    {
        $u = $this->em->find(User::class, $id);
        if ($u === null) {
            return new JsonResponse(['title' => 'Compte inconnu.'], Response::HTTP_NOT_FOUND);
        }
        if ($u->getId() === $admin->getId()) {
            return new JsonResponse(['title' => 'Tu ne peux pas supprimer ton propre compte d’ici : utilise « Mon compte » dans Réglages.'], Response::HTTP_CONFLICT);
        }
        if ($this->lastAdmin($u)) {
            return new JsonResponse(['title' => 'C’est le seul administrateur : il ne peut pas être supprimé.'], Response::HTTP_CONFLICT);
        }
        $label = $u->getEmail() ?? $u->getUsername();
        if (!hash_equals(mb_strtolower($label), mb_strtolower(trim((string) ($r->toArray()['confirm'] ?? ''))))) {
            return new JsonResponse(['title' => 'Recopie l’adresse du compte pour confirmer : rien n’a été supprimé.', 'field' => 'confirm'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $this->tokens->revokeAll($u);
        $this->em->remove($u);
        $this->em->flush();
        $this->audit->record($admin, 'user.deleted', $label);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function toggle(string $id, User $admin, bool $disabled): JsonResponse
    {
        $u = $this->em->find(User::class, $id);
        if ($u === null) {
            return new JsonResponse(['title' => 'Compte inconnu.'], Response::HTTP_NOT_FOUND);
        }
        if ($disabled && $u->getId() === $admin->getId()) {
            return new JsonResponse(['title' => 'Tu ne peux pas désactiver ton propre compte.'], Response::HTTP_CONFLICT);
        }
        if ($disabled && $this->lastAdmin($u)) {
            return new JsonResponse(['title' => 'C’est le seul administrateur : il ne peut pas être désactivé.'], Response::HTTP_CONFLICT);
        }
        $u->setDisabled($disabled);
        $this->em->flush();
        if ($disabled) {
            $this->tokens->revokeAll($u); // déconnecté partout, tout de suite
        }
        $this->audit->record($admin, $disabled ? 'user.disabled' : 'user.enabled', $u->getEmail() ?? $u->getUsername());

        return new JsonResponse(['id' => $u->getId(), 'disabled' => $disabled]);
    }

    /** Vrai si ce compte est le dernier administrateur actif. */
    private function lastAdmin(User $u): bool
    {
        return $u->isInstanceAdmin() && $this->em->getRepository(User::class)->count(['instanceAdmin' => true, 'disabled' => false]) <= 1;
    }

    private function invalid(string $field, string $title, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return new JsonResponse(['title' => $title, 'field' => $field], $status);
    }
}
