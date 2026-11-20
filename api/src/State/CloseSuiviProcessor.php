<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Suivi;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProcessorInterface<Suivi, null> */
final class CloseSuiviProcessor implements ProcessorInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!$data instanceof Suivi) {
            // Introuvable ou appartenant à un autre utilisateur : on ne distingue pas les deux.
            throw new NotFoundHttpException('Suivi inconnu.');
        }
        $data->close();
        $this->em->flush();

        return null;
    }
}
