<?php

namespace App\Command;

use App\Mail\MailSynchronizer;
use App\Mail\MailboxReaderFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:mail:sync', description: 'Synchronise la boîte IMAP configurée (incrémental, par UID) et signale les réponses.')]
final class SyncMailCommand extends Command
{
    public function __construct(private readonly MailSynchronizer $sync, private readonly MailboxReaderFactory $factory)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $r = $this->sync->sync($this->factory->account(), $this->factory->create());
        } catch (\Throwable $e) {
            // Message clair, jamais un code technique seul (F-79).
            $io->error(sprintf('Je n’ai pas pu joindre le serveur de messagerie (%s). Vérifie qu’il tourne et que les identifiants sont bons.', $e->getMessage()));

            return Command::FAILURE;
        }
        $io->success(sprintf('%d nouveaux messages, %d suivi(s) avec une réponse reçue.', $r['fetched'], $r['replies']));

        return Command::SUCCESS;
    }
}
