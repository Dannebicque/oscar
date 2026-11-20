<?php

namespace App\Repository;

use App\Entity\Mail;
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
    public function lastUid(string $account, string $folder): int
    {
        return (int) $this->createQueryBuilder('m')->select('MAX(m.uid)')
            ->where('m.account = :a AND m.folder = :f')->setParameter('a', $account)->setParameter('f', $folder)
            ->getQuery()->getSingleScalarResult();
    }

    /** @return list<Mail> messages reçus (hors envoyés) d'un compte */
    public function inbound(string $account): array
    {
        return $this->findBy(['account' => $account, 'folder' => 'INBOX']);
    }
}
