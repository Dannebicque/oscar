<?php

namespace App\Controller;

use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Entity\User;
use App\Mail\MailboxFolders;
use App\Mail\MailboxReaderFactory;
use App\Mail\MailSynchronizer;
use App\Repository\MailRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Dossiers et sous-dossiers de la messagerie (F-16). Ce sont ceux du serveur IMAP : on les voit aussi dans tout autre client.
 * Rien n'est supprimé définitivement : un dossier ne se supprime que vide, et il ne se renomme que s'il est personnel.
 */
final class MailFolderController extends AbstractController
{
    /** Dossiers que l'application connaît déjà : on n'y touche pas. */
    private const SYSTEM = ['inbox', 'sent', 'drafts', 'archive', 'trash'];

    #[Route('/api/mail-folders', methods: ['GET'])]
    public function list(#[CurrentUser] User $user, EntityManagerInterface $em, MailboxReaderFactory $factory): JsonResponse
    {
        [$account, $reader] = $this->reader($user, $em, $factory);
        if ($reader === false) {
            return $this->down();
        }
        if ($reader === null) {
            return new JsonResponse([]);
        }
        try {
            $rows = $reader->folders();
        } catch (\Throwable) {
            return $this->down();
        }
        $counts = [];
        foreach ($em->getRepository(Mail::class)->createQueryBuilder('m')->select('m.folder AS f, COUNT(m.id) AS n')->where('m.account = :a')->setParameter('a', $account->getId())->groupBy('m.folder')->getQuery()->getArrayResult() as $r) {
            $counts[$r['f']] = (int) $r['n'];
        }

        return new JsonResponse(array_values(array_map(static fn (array $f) => $f + ['system' => self::isSystem($f['path']), 'count' => $counts[$f['path']] ?? 0], $rows)));
    }

    #[Route('/api/mail-folders', methods: ['POST'])]
    public function create(Request $r, #[CurrentUser] User $user, EntityManagerInterface $em, MailboxReaderFactory $factory): JsonResponse
    {
        $in = $r->toArray();
        $name = trim((string) ($in['name'] ?? ''));
        $parent = isset($in['parent']) && $in['parent'] !== '' ? (string) $in['parent'] : null;
        if ($bad = $this->badName($name)) {
            return $bad;
        }
        [, $reader] = $this->reader($user, $em, $factory);
        if ($reader === false) {
            return $this->down();
        }
        if ($reader === null) {
            return new JsonResponse(['title' => 'Aucun compte de messagerie n’est configuré.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        try {
            $all = $reader->folders();
            $delimiter = $all[0]['delimiter'] ?? '/';
            if ($parent !== null) {
                $found = array_values(array_filter($all, static fn (array $f) => $f['path'] === $parent));
                if ($found === [] || self::isSystem($parent)) {
                    return new JsonResponse(['title' => 'Le dossier parent est introuvable, ou c’est un dossier de base.', 'field' => 'parent'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $delimiter = $found[0]['delimiter'];
            }
            $path = $parent !== null ? $parent.$delimiter.$name : $name;
            if (array_filter($all, static fn (array $f) => mb_strtolower($f['path']) === mb_strtolower($path)) !== [] || self::isSystem($path)) {
                return new JsonResponse(['title' => 'Un dossier porte déjà ce nom.', 'field' => 'name'], Response::HTTP_CONFLICT);
            }
            $reader->createFolder($path);
        } catch (\Throwable) {
            return $this->down();
        }

        return new JsonResponse(['path' => $path, 'name' => $name, 'delimiter' => $delimiter, 'system' => false, 'count' => 0], Response::HTTP_CREATED);
    }

    #[Route('/api/mail-folders', methods: ['PATCH'])]
    public function rename(Request $r, #[CurrentUser] User $user, EntityManagerInterface $em, MailboxReaderFactory $factory): JsonResponse
    {
        $in = $r->toArray();
        $path = (string) ($in['path'] ?? '');
        $name = trim((string) ($in['name'] ?? ''));
        if ($bad = $this->badName($name)) {
            return $bad;
        }
        if (self::isSystem($path)) {
            return new JsonResponse(['title' => 'Les dossiers de base ne se renomment pas.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        [$account, $reader] = $this->reader($user, $em, $factory);
        if ($reader === false) {
            return $this->down();
        }
        if ($reader === null) {
            return new JsonResponse(['title' => 'Aucun compte de messagerie n’est configuré.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        try {
            $all = $reader->folders();
            $current = array_values(array_filter($all, static fn (array $f) => $f['path'] === $path));
            if ($current === []) {
                return new JsonResponse(['title' => 'Dossier introuvable.'], Response::HTTP_NOT_FOUND);
            }
            $d = $current[0]['delimiter'];
            $cut = strrpos($path, $d);
            $new = ($cut === false ? '' : substr($path, 0, $cut + \strlen($d))).$name;
            if ($new === $path) {
                return new JsonResponse(['path' => $path, 'name' => $name]);
            }
            if (array_filter($all, static fn (array $f) => mb_strtolower($f['path']) === mb_strtolower($new)) !== []) {
                return new JsonResponse(['title' => 'Un dossier porte déjà ce nom.', 'field' => 'name'], Response::HTTP_CONFLICT);
            }
            $reader->renameFolder($path, $new);
        } catch (\Throwable) {
            return $this->down();
        }
        // Le serveur a renommé : les messages que nous connaissons suivent (le dossier et ses sous-dossiers).
        $moved = $em->getRepository(Mail::class)->createQueryBuilder('m')->where('m.account = :a AND (m.folder = :old OR m.folder LIKE :like)')
            ->setParameter('a', $account->getId())->setParameter('old', $path)->setParameter('like', addcslashes($path.$d, '%_\\').'%')->getQuery()->getResult();
        foreach ($moved as $m) {
            /** @var Mail $m */
            $m->relocate($new.substr($m->getFolder(), \strlen($path)), $m->getUid());
        }
        $em->flush();

        return new JsonResponse(['path' => $new, 'name' => $name]);
    }

    #[Route('/api/mail-folders', methods: ['DELETE'])]
    public function delete(Request $r, #[CurrentUser] User $user, EntityManagerInterface $em, MailboxReaderFactory $factory): JsonResponse
    {
        $path = (string) $r->query->get('path', '');
        if (self::isSystem($path)) {
            return new JsonResponse(['title' => 'Les dossiers de base ne se suppriment pas.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        [$account, $reader] = $this->reader($user, $em, $factory);
        if ($reader === false) {
            return $this->down();
        }
        if ($reader === null) {
            return new JsonResponse(['title' => 'Aucun compte de messagerie n’est configuré.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        try {
            $all = $reader->folders();
            $found = array_values(array_filter($all, static fn (array $f) => $f['path'] === $path));
            if ($found === []) {
                return new JsonResponse(['title' => 'Dossier introuvable.'], Response::HTTP_NOT_FOUND);
            }
            $d = $found[0]['delimiter'];
            if (array_filter($all, static fn (array $f) => str_starts_with($f['path'], $path.$d)) !== []) {
                return new JsonResponse(['title' => 'Ce dossier contient des sous-dossiers. Vide-les ou supprime-les d’abord.'], Response::HTTP_CONFLICT);
            }
            // Jamais de suppression de messages : seul un dossier vide, côté serveur comme côté Oscar, peut partir.
            $local = $em->getRepository(Mail::class)->count(['account' => $account, 'folder' => $path]);
            if ($local > 0 || $reader->countMessages($path) > 0) {
                return new JsonResponse(['title' => 'Ce dossier n’est pas vide. Déplace ses messages ailleurs avant de le supprimer.'], Response::HTTP_CONFLICT);
            }
            $reader->deleteFolder($path);
        } catch (\Throwable) {
            return $this->down();
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** Ouvrir un dossier : on récupère ses derniers messages (100 au plus à la première visite), puis seulement les nouveaux. */
    #[Route('/api/mail-folders/sync', methods: ['POST'])]
    public function sync(Request $r, #[CurrentUser] User $user, EntityManagerInterface $em, MailboxReaderFactory $factory, MailRepository $mails, MailSynchronizer $sync): JsonResponse
    {
        $path = (string) ($r->toArray()['path'] ?? '');
        [$account, $reader] = $this->reader($user, $em, $factory);
        if ($reader === false) {
            return $this->down();
        }
        if ($reader === null) {
            return new JsonResponse(['fetched' => 0]);
        }
        try {
            if (array_filter($reader->folders(), static fn (array $f) => $f['path'] === $path) === []) {
                return new JsonResponse(['title' => 'Dossier introuvable.'], Response::HTTP_NOT_FOUND);
            }
            $last = $mails->lastUid($account, $path);
            $headers = $last === 0 ? $reader->fetchRecent($path, 100) : $reader->fetchAfter($path, $last);
        } catch (\Throwable) {
            return $this->down();
        }

        return new JsonResponse(['fetched' => $sync->import($account, $headers)]);
    }

    public static function isSystem(string $path): bool
    {
        return \in_array(mb_strtolower($path), self::SYSTEM, true);
    }

    private function badName(string $name): ?JsonResponse
    {
        if ($name === '' || mb_strlen($name) > 60 || preg_match('/[\\/\\\\.%*#&]/u', $name) || preg_match('/^\s|\s$/u', $name)) {
            return new JsonResponse(['title' => 'Donne un nom de 1 à 60 caractères, sans / \\ . % * # &.', 'field' => 'name'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }

    private function down(): JsonResponse
    {
        return new JsonResponse(['title' => 'Le serveur de messagerie ne répond pas. Rien n’a bougé, réessaie dans un instant.'], Response::HTTP_BAD_GATEWAY);
    }

    /** @return array{0: ?MailAccount, 1: MailboxFolders|false|null} le premier compte de l'utilisateur ; false si le serveur est injoignable */
    private function reader(User $user, EntityManagerInterface $em, MailboxReaderFactory $factory): array
    {
        $account = $em->getRepository(MailAccount::class)->findOneBy(['owner' => $user], ['id' => 'ASC']);
        if ($account === null) {
            return [null, null];
        }

        try {
            return [$account, $factory->create($account)];
        } catch (\Throwable) {
            return [$account, false];
        }
    }
}
