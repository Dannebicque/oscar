<?php

namespace App\Command;

use App\Entity\Suivi;
use App\Enum\SuiviState;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:suivis:seed', description: 'Insère les suivis de démonstration (base vide uniquement).')]
final class SeedSuivisCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->em->getRepository(Suivi::class)->count([]) > 0) {
            $io->note('Des suivis existent déjà : rien à faire.');

            return Command::SUCCESS;
        }

        $rows = [
            ['Planning des soutenances', 'Sophie Laurent', SuiviState::ToHandle, '2026-11-20', false],
            ['Devis des affiches', 'Marc Dubois', SuiviState::RelanceDue, '2026-11-19', true],
            ['Salle B104 pour le jury', 'Scolarité', SuiviState::Late, '2026-11-17', false],
            ['Convention de stage', 'Claire Petit', SuiviState::Waiting, '2026-11-24', false],
            ['Matériel de TP', 'Service achats', SuiviState::Done, '2026-11-12', false],
        ];
        foreach ($rows as [$subject, $contact, $state, $due, $ai]) {
            $this->em->persist(
                (new Suivi())->setSubject($subject)->setContact($contact)->setState($state)
                    ->setDueAt(new \DateTimeImmutable($due))->setSuggestedByAi($ai)
            );
        }
        $this->em->flush();
        $io->success(\count($rows).' suivis créés.');

        return Command::SUCCESS;
    }
}
