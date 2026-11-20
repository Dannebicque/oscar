<?php

namespace App\Controller;

use App\Entity\MailAccount;
use App\Mail\MailboxReaderFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** « Tester la connexion » (Réglages > Comptes) : message clair et action proposée, jamais un code seul (F-79). */
final class MailAccountTestController extends AbstractController
{
    #[Route('/api/mail-accounts/{id}/test', methods: ['POST'])]
    public function __invoke(string $id, EntityManagerInterface $em, MailboxReaderFactory $factory, Security $security): JsonResponse
    {
        $a = $em->find(MailAccount::class, $id);
        if ($a === null || $a->getOwner()?->getId() !== $security->getUser()?->getId()) {
            return new JsonResponse(['title' => 'Compte inconnu'], Response::HTTP_NOT_FOUND);
        }
        try {
            $factory->create($a)->fetchAfter('INBOX', PHP_INT_MAX - 1);
        } catch (\Throwable $e) {
            $a->markFailed($e->getMessage());
            $em->flush();

            return new JsonResponse(['ok' => false, 'message' => 'Connexion impossible. Vérifie le serveur, le port, le chiffrement et le mot de passe, puis réessaie.']);
        }

        return new JsonResponse(['ok' => true, 'message' => 'Connexion réussie.']);
    }
}
