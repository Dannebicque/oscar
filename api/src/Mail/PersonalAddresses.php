<?php

namespace App\Mail;

use App\Entity\MailAccount;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Une adresse personnelle (compte Oscar ou messagerie connectée) ne devient jamais une boîte partagée :
 * sinon quelqu'un pourrait faire remonter chez lui les demandes reçues par un autre (§2, mails personnels privés).
 */
final class PersonalAddresses
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function isPersonal(string $address): bool
    {
        $a = strtolower(trim($address));
        foreach ([[User::class, 'u.email'], [User::class, 'u.username'], [MailAccount::class, 'u.address']] as [$class, $field]) {
            if ((int) $this->em->createQueryBuilder()->select('COUNT(u)')->from($class, 'u')->where("LOWER($field) = :a")->setParameter('a', $a)->getQuery()->getSingleScalarResult() > 0) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> les adresses propres à cet utilisateur */
    public function of(User $user): array
    {
        $mine = [(string) $user->getEmail(), $user->getUsername()];
        foreach ($this->em->getRepository(MailAccount::class)->findBy(['owner' => $user]) as $account) {
            $mine[] = $account->getAddress();
        }

        return array_values(array_filter(array_map(strtolower(...), $mine)));
    }
}
