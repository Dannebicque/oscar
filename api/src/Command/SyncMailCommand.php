<?php

namespace App\Command;

use App\Entity\MailAccount;
use App\Mail\MailboxReaderFactory;
use App\Mail\MailSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:mail:sync', description: 'Synchronise les comptes IMAP (incrémental, par UID) et signale les réponses.')]
final class SyncMailCommand extends Command
{
    public function __construct(
        private readonly MailSynchronizer $sync,
        private readonly MailboxReaderFactory $factory,
        private readonly EntityManagerInterface $em,
        private readonly \App\Mail\ConnectionMonitor $monitor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('user', null, InputOption::VALUE_REQUIRED, 'Se limiter à un utilisateur (identifiant)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $criteria = $input->getOption('user') ? ['owner' => $this->em->getRepository(\App\Entity\User::class)->findOneBy(['username' => $input->getOption('user')])] : [];
        $accounts = $this->em->getRepository(MailAccount::class)->findBy($criteria);
        if ($accounts === []) {
            $io->warning('Aucun compte de messagerie. Ajoute-en un (php bin/console app:dev:setup en local).');

            return Command::SUCCESS;
        }
        $failed = 0;
        foreach ($accounts as $a) {
            try {
                $r = $this->sync->sync($a, $this->factory->create($a));
                $this->monitor->success($a);
                $io->success(sprintf('%s : %d nouveaux messages, %d suivi(s) avec une réponse reçue.', $a->getLabel(), $r['fetched'], $r['replies']));
            } catch (\Throwable $e) {
                ++$failed;
                $this->monitor->failure($a, 'sync', $e);
                // Message clair, jamais un code technique seul (F-79).
                $io->error(sprintf('%s : je n’ai pas pu joindre le serveur de messagerie (%s). Vérifie qu’il tourne et que les identifiants sont bons.', $a->getLabel(), $e->getMessage()));
            }
        }

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
