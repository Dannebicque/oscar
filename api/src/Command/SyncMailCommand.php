<?php

namespace App\Command;

use App\Mail\MailSynchronizer;
use App\Mail\WebklexMailboxReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:mail:sync', description: 'Synchronise la boîte IMAP configurée (incrémental, par UID) et signale les réponses.')]
final class SyncMailCommand extends Command
{
    public function __construct(
        private readonly MailSynchronizer $sync,
        #[Autowire('%env(IMAP_HOST)%')] private readonly string $host,
        #[Autowire('%env(int:IMAP_PORT)%')] private readonly int $port,
        #[Autowire('%env(IMAP_USER)%')] private readonly string $user,
        #[Autowire('%env(IMAP_PASSWORD)%')] private readonly string $password,
        #[Autowire('%env(default::IMAP_ENCRYPTION)%')] private readonly ?string $encryption = null,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $reader = new WebklexMailboxReader($this->host, $this->port, $this->user, $this->password, $this->encryption ?: 'none', ($this->encryption ?: 'none') !== 'none');
        try {
            $r = $this->sync->sync($this->user, $reader);
        } catch (\Throwable $e) {
            // Message clair, jamais un code technique seul (F-79).
            $io->error(sprintf('Je n’ai pas pu joindre %s:%d (%s). Vérifie que le serveur tourne et que les identifiants sont bons.', $this->host, $this->port, $e->getMessage()));

            return Command::FAILURE;
        }
        $io->success(sprintf('%d nouveaux messages, %d suivi(s) avec une réponse reçue.', $r['fetched'], $r['replies']));

        return Command::SUCCESS;
    }
}
