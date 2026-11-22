<?php

namespace App\Repository;

use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Mail> */
class MailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mail::class);
    }

    /** Dernier UID synchronisé : la synchro reprend juste après (incrémentale). */
    public function lastUid(MailAccount $account, string $folder): int
    {
        return (int) $this->createQueryBuilder('m')->select('MAX(m.uid)')
            ->where('m.account = :a AND m.folder = :f')->setParameter('a', $account->getId())->setParameter('f', $folder)
            ->getQuery()->getSingleScalarResult();
    }

    /**
     * Dossiers que l'utilisateur a ouverts ou où il a rangé des messages (Archive, Corbeille et dossiers personnels) :
     * la synchro y cherche aussi les nouveaux messages. Jamais un dossier dont rien n'est connu (pas d'import massif par surprise).
     *
     * @return list<string>
     */
    public function knownFolders(MailAccount $account): array
    {
        $rows = $this->createQueryBuilder('m')->select('DISTINCT m.folder AS f')->where('m.account = :a')->setParameter('a', $account->getId())->getQuery()->getArrayResult();

        return array_values(array_filter(array_column($rows, 'f'), static fn (string $f) => !\in_array($f, ['INBOX', 'Sent', 'Drafts'], true)));
    }

    /** @return list<Mail> messages reçus (hors envoyés) de tous les comptes d'un utilisateur */
    public function inbound(User $owner): array
    {
        return $this->findBy(['owner' => $owner, 'folder' => 'INBOX']);
    }
}
