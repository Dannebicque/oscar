<?php

namespace App\Command;

use App\Calendar\CalendarSynchronizer;
use App\Entity\CalendarAccount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:calendar:sync', description: 'Synchronise les agendas CalDAV.')]
final class SyncCalendarCommand extends Command
{
    public function __construct(private readonly CalendarSynchronizer $sync, private readonly EntityManagerInterface $em, private readonly \App\Mail\ConnectionMonitor $monitor)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $failed = 0;
        foreach ($this->em->getRepository(CalendarAccount::class)->findAll() as $a) {
            try {
                $s = $this->sync->sync($a);
                $this->monitor->success($a);
                $io->success(sprintf('%s : %d ajoutés, %d mis à jour, %d supprimés.', $a->getLabel(), $s['added'], $s['updated'], $s['removed']));
            } catch (\Throwable $e) {
                ++$failed;
                $this->monitor->failure($a, 'calendar', $e);
                $io->error(sprintf('%s : je n’ai pas pu joindre l’agenda (%s).', $a->getLabel(), $e->getMessage()));
            }
        }

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
