<?php

namespace App\Command;

use App\Entity\User;
use App\Instance\SetupToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:setup:token', description: 'Affiche le code d’installation (créé au besoin) tant qu’aucun administrateur n’existe.')]
final class SetupTokenCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly SetupToken $token)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->em->getRepository(User::class)->count(['instanceAdmin' => true]) > 0) {
            $this->token->discard();
            $io->writeln('Instance déjà installée.');

            return Command::SUCCESS;
        }
        $io->writeln('');
        $io->writeln('  Installation : ouvre l’adresse de ton instance, puis saisis ce code d’installation :');
        $io->writeln('');
        $io->writeln('      '.$this->token->ensure());
        $io->writeln('');

        return Command::SUCCESS;
    }
}
