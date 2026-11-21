<?php

namespace App\Controller;

use App\Directory\Directory;
use App\Entity\User;
use App\Entity\UserSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Annuaire de l'établissement pour l'autocomplétion des destinataires (F-18, F-73). */
final class DirectoryController extends AbstractController
{
    public function __construct(private readonly Directory $directory, private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/directory/status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return new JsonResponse(['configured' => $this->directory->isConfigured()]);
    }

    #[Route('/api/directory', methods: ['GET'])]
    public function search(Request $r, #[CurrentUser] User $user): JsonResponse
    {
        $s = ($this->em->getRepository(UserSettings::class)->findOneBy(['owner' => $user]) ?? new UserSettings($user))->all();
        try {
            return new JsonResponse($this->directory->search((string) $r->query->get('q', ''), (bool) $s['dirShowDepartment'], (bool) $s['dirShowPhone']));
        } catch (\Throwable) {
            return new JsonResponse(['title' => 'L’annuaire ne répond pas. Tu peux saisir l’adresse à la main.'], Response::HTTP_BAD_GATEWAY);
        }
    }
}
