<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Suivi;
use Doctrine\ORM\EntityManagerInterface;

/** @implements ProcessorInterface<Suivi, null> */
final class CloseSuiviProcessor implements ProcessorInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        \assert($data instanceof Suivi);
        $data->close();
        $this->em->flush();

        return null;
    }
}
