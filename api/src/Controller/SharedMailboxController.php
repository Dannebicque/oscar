<?php

namespace App\Controller;

use App\Entity\Mail;
use App\Entity\Notification;
use App\Entity\SharedMailbox;
use App\Entity\SharedMailboxMember;
use App\Entity\Suivi;
use App\Entity\User;
use App\Mail\PersonalAddresses;
use App\Notification\NotificationCenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Boîtes partagées : membres et rôles (F-40). Un membre voit sa boîte ; un administrateur gère les membres et l'IA (§2, §5). */
final class SharedMailboxController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly PersonalAddresses $personal, private readonly NotificationCenter $center)
    {
    }

    #[Route('/api/shared-mailboxes', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        $out = [];
        foreach ($this->em->getRepository(SharedMailboxMember::class)->findBy(['user' => $user]) as $m) {
            $out[] = $this->view($m->getMailbox(), $user);
        }

        return new JsonResponse($out);
    }

    #[Route('/api/shared-mailboxes', methods: ['POST'])]
    public function create(Request $r, #[CurrentUser] User $user): JsonResponse
    {
        $in = $r->toArray();
        $address = strtolower(trim((string) ($in['address'] ?? '')));
        $slug = strtolower(trim((string) ($in['slug'] ?? '')));
        // L'identifiant court se déduit de l'adresse (contact@ → « contact », puis « contact-2 » s'il est pris).
        if ($slug === '') {
            $base = substr(trim((string) preg_replace('/[^a-z0-9]+/', '-', explode('@', $address)[0]), '-'), 0, 36) ?: 'boite';
            $base = $base === 'personal' ? 'boite' : $base;
            for ($slug = $base, $n = 2; $this->em->getRepository(SharedMailbox::class)->findOneBy(['slug' => $slug]) !== null; ++$n) {
                $slug = $base.'-'.$n;
            }
        }
        if (!filter_var($address, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-z0-9-]{1,40}$/', $slug) || trim((string) ($in['name'] ?? '')) === '' || $slug === 'personal') {
            return new JsonResponse(['title' => 'Adresse, nom ou identifiant invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($this->em->getRepository(SharedMailbox::class)->findOneBy(['address' => $address]) !== null || $this->em->getRepository(SharedMailbox::class)->findOneBy(['slug' => $slug]) !== null) {
            return new JsonResponse(['title' => 'Cette boîte partagée existe déjà.'], Response::HTTP_CONFLICT);
        }
        if ($this->personal->isPersonal($address)) {
            return new JsonResponse(['title' => 'C’est une adresse personnelle : une boîte partagée doit être une adresse commune (alias).'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $box = new SharedMailbox($address, trim((string) $in['name']), $slug);
        $box->addMember($user, 'admin');
        $this->em->persist($box);
        $this->em->flush();

        return new JsonResponse($this->view($box, $user), Response::HTTP_CREATED);
    }

    #[Route('/api/shared-mailboxes/{id}', methods: ['PATCH'])]
    public function update(string $id, Request $r, #[CurrentUser] User $user): JsonResponse
    {
        if (($box = $this->adminBox($id, $user)) === null) {
            return $this->forbidden($id, $user);
        }
        $in = $r->toArray();
        isset($in['autoFollow']) && \is_bool($in['autoFollow']) && $box->setAutoFollow($in['autoFollow']);
        isset($in['aiEnabled']) && \is_bool($in['aiEnabled']) && $box->setAiEnabled($in['aiEnabled']);
        // Identité d'envoi de l'équipe (réponses « De : contact@ ») : réservée aux administrateurs, comme le reste de la boîte.
        if (\array_key_exists('senderName', $in)) {
            if (!\is_string($in['senderName']) && $in['senderName'] !== null || mb_strlen((string) $in['senderName']) > 100 || preg_match('/[\r\n<>"]/', (string) $in['senderName'])) {
                return new JsonResponse(['title' => 'Donne un nom de 100 caractères au plus, sans < > ni guillemets.', 'field' => 'senderName'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $box->setSenderName($in['senderName']);
        }
        if (\array_key_exists('signature', $in)) {
            if (!\is_string($in['signature']) && $in['signature'] !== null || mb_strlen((string) $in['signature']) > 2000) {
                return new JsonResponse(['title' => 'La signature ne doit pas dépasser 2 000 caractères.', 'field' => 'signature'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $box->setSignature($in['signature']);
        }
        $this->em->flush();

        return new JsonResponse($this->view($box, $user));
    }

    #[Route('/api/shared-mailboxes/{id}/members', methods: ['POST'])]
    public function addMember(string $id, Request $r, #[CurrentUser] User $user): JsonResponse
    {
        if (($box = $this->adminBox($id, $user)) === null) {
            return $this->forbidden($id, $user);
        }
        $in = $r->toArray();
        $wanted = strtolower(trim((string) ($in['username'] ?? '')));
        $target = $wanted === '' ? null : $this->em->createQueryBuilder()->select('u')->from(User::class, 'u')
            ->where('LOWER(u.username) = :w OR LOWER(u.email) = :w')->setParameter('w', $wanted)->setMaxResults(1)->getQuery()->getOneOrNullResult();
        $role = (string) ($in['role'] ?? 'member');
        if (!$target instanceof User || !\in_array($role, ['member', 'admin'], true)) {
            return new JsonResponse(['title' => 'Je ne trouve personne avec cet identifiant. La personne doit avoir un compte ici.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $existing = $box->membershipOf($target);
        if ($existing?->isAccepted() && $existing->isAdmin() && $role !== 'admin' && $this->adminCount($box) <= 1) {
            return new JsonResponse(['title' => 'Il faut au moins un administrateur.'], Response::HTTP_CONFLICT);
        }
        $member = $box->addMember($target, $role)->setRole($role);
        if ($existing === null) {
            // Une invitation : rien n'est partagé tant que la personne n'a pas dit oui.
            $member->setAccepted(false);
            $this->center->deliver($target, new Notification('sharedAdded', ['who' => $user->getDisplayName(), 'subject' => $box->getAddress()], 'reglages', 'shared:'.$box->getId().':'.$target->getId().':'.time()));
        }
        $this->em->flush();

        return new JsonResponse($this->view($box, $user), Response::HTTP_CREATED);
    }

    /** Accepter une invitation. Pour la refuser : se retirer, comme pour quitter la boîte. */
    #[Route('/api/shared-mailboxes/{id}/accept', methods: ['POST'])]
    public function accept(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $box = $this->em->find(SharedMailbox::class, $id);
        $member = $box?->membershipOf($user);
        if ($box === null || $member === null) {
            return new JsonResponse(['title' => 'Boîte partagée inconnue.'], Response::HTTP_NOT_FOUND);
        }
        $member->setAccepted(true);
        $this->em->flush();

        return new JsonResponse($this->view($box, $user));
    }

    #[Route('/api/shared-mailboxes/{id}/members/{username}', methods: ['DELETE'])]
    public function removeMember(string $id, string $username, #[CurrentUser] User $user): JsonResponse
    {
        // Chacun peut quitter une boîte ; retirer quelqu'un d'autre est réservé aux administrateurs.
        $leaving = $username === $user->getUsername();
        $box = $leaving ? $this->em->find(SharedMailbox::class, $id) : $this->adminBox($id, $user);
        if ($box === null || ($leaving && $box->membershipOf($user) === null)) {
            return $this->forbidden($id, $user);
        }
        $member = null;
        foreach ($box->getMembers() as $m) {
            $m->getUser()->getUsername() === $username && $member = $m;
        }
        if ($member === null) {
            return new JsonResponse(['title' => 'Ce membre n’existe pas.'], Response::HTTP_NOT_FOUND);
        }
        if ($member->isAccepted() && $member->isAdmin() && $this->adminCount($box) <= 1) {
            return new JsonResponse(['title' => 'Il faut au moins un administrateur. Nomme quelqu’un d’autre avant.'], Response::HTTP_CONFLICT);
        }
        // Ses exemplaires des mails redeviennent des mails personnels : sinon ils disparaîtraient de son écran.
        $this->release($box, $member->getUser());
        $box->getMembers()->removeElement($member);
        $this->em->flush();

        return $leaving ? new JsonResponse(null, Response::HTTP_NO_CONTENT) : new JsonResponse($this->view($box, $user));
    }

    /** Supprimer une boîte créée par erreur. Dès qu'elle porte des demandes, leur historique est protégé. */
    #[Route('/api/shared-mailboxes/{id}', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] User $user): JsonResponse
    {
        if (($box = $this->adminBox($id, $user)) === null) {
            return $this->forbidden($id, $user);
        }
        if ($this->em->getRepository(Suivi::class)->count(['sharedMailbox' => $box]) > 0) {
            return new JsonResponse(['title' => 'Cette boîte a des demandes : je la garde pour ne pas perdre leur historique. Tu peux retirer ses membres.'], Response::HTTP_CONFLICT);
        }
        $this->release($box);
        $this->em->remove($box);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function release(SharedMailbox $box, ?User $only = null): void
    {
        foreach ($this->em->getRepository(Mail::class)->findBy(['sharedMailbox' => $box] + ($only === null ? [] : ['owner' => $only])) as $mail) {
            $mail->setSharedMailbox(null);
        }
    }

    private function adminCount(SharedMailbox $box): int
    {
        return \count(array_filter($box->getAcceptedMembers(), static fn (SharedMailboxMember $m) => $m->isAdmin()));
    }

    private function adminBox(string $id, User $user): ?SharedMailbox
    {
        $box = $this->em->find(SharedMailbox::class, $id);

        return $box?->memberOf($user)?->isAdmin() ? $box : null;
    }

    /** 404 pour un non-membre (la boîte n'existe pas pour lui), 403 pour un membre sans droit d'administration. */
    private function forbidden(string $id, User $user): JsonResponse
    {
        $box = $this->em->find(SharedMailbox::class, $id);

        return $box?->memberOf($user) === null
            ? new JsonResponse(['title' => 'Boîte partagée inconnue.'], Response::HTTP_NOT_FOUND)
            : new JsonResponse(['title' => 'Seul un administrateur de la boîte peut faire cela.'], Response::HTTP_FORBIDDEN);
    }

    /** @return array<string, mixed> */
    private function view(SharedMailbox $b, User $me): array
    {
        $members = array_map(static fn (SharedMailboxMember $m) => ['username' => $m->getUser()->getUsername(), 'displayName' => $m->getUser()->getDisplayName(), 'role' => $m->getRole(), 'pending' => !$m->isAccepted()], $b->getMembers()->toArray());
        // Une personne invitée voit de quoi décider (la boîte et qui en fait partie), pas de quoi s'en servir.
        if ($b->memberOf($me) === null) {
            return ['id' => $b->getId(), 'slug' => $b->getSlug(), 'address' => $b->getAddress(), 'name' => $b->getName(), 'invited' => true, 'role' => $b->membershipOf($me)?->getRole(), 'aiEnabled' => $b->isAiEnabled(), 'members' => $members];
        }

        return [
            'id' => $b->getId(), 'slug' => $b->getSlug(), 'address' => $b->getAddress(), 'name' => $b->getName(),
            'senderName' => $b->getSenderName(), 'signature' => $b->getSignature(), 'identityId' => 'shared:'.$b->getId(),
            'role' => $b->memberOf($me)?->getRole(), 'autoFollow' => $b->isAutoFollow(), 'aiEnabled' => $b->isAiEnabled(),
            'invited' => false, 'members' => $members,
        ];
    }
}
