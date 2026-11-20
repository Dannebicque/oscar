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

    /** @return list<Mail> messages reçus (hors envoyés) de tous les comptes d'un utilisateur */
    public function inbound(User $owner): array
    {
        return $this->findBy(['owner' => $owner, 'folder' => 'INBOX']);
    }
}
