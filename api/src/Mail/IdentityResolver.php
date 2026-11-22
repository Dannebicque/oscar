<?php

namespace App\Mail;

use App\Entity\Identity;
use App\Entity\MailAccount;
use App\Entity\SharedMailbox;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Retrouve l'identité d'envoi demandée : une identité personnelle du compte, ou celle d'une boîte partagée dont l'utilisateur est membre
 * (`shared:<id>` : « De : contact@ »). Jamais l'identité d'un autre utilisateur ni d'une boîte dont on n'est pas membre.
 */
final class IdentityResolver
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @throws \InvalidArgumentException si elle n'existe pas ou n'est pas utilisable par cet utilisateur avec ce compte */
    public function resolve(User $user, MailAccount $account, ?string $id): ?SenderIdentity
    {
        if ($id === null || $id === '') {
            return null;
        }
        if (str_starts_with($id, 'shared:')) {
            $box = $this->em->find(SharedMailbox::class, substr($id, 7));
            if ($box === null || $box->memberOf($user) === null) {
                throw new \InvalidArgumentException('Identité inconnue.');
            }

            return new SenderIdentity($box->getAddress(), $box->getSenderName() ?? $box->getName(), null, $box->getSignature());
        }
        $i = $this->em->find(Identity::class, $id);
        if ($i === null || $i->getOwner()->getId() !== $user->getId() || $i->getAccount()->getId() !== $account->getId()) {
            throw new \InvalidArgumentException('Identité inconnue.');
        }

        return new SenderIdentity($i->getAddress(), $i->getName(), $i->getReplyTo(), $i->getSignature(), $i->getSignatureHtml());
    }
}
