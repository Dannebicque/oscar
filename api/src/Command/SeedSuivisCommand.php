<?php

namespace App\Command;

use App\Entity\Suivi;
use App\Enum\SuiviDirection;
use App\Enum\SuiviStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use App\Entity\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:suivis:seed', description: 'Insère les suivis de démonstration d’un utilisateur (sans effet s’il en a déjà).')]
final class SeedSuivisCommand extends Command
{
    /** Message-ID des mails de test de docker/dovecot : la synchro IMAP les rattache à ces suivis. */
    private const MAIL_IDS = [
        'Devis des affiches' => '<devis-affiches-1@iut-troyes.fr>',
        'Convention de stage' => '<convention-1@iut-troyes.fr>',
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('user', null, InputOption::VALUE_REQUIRED, 'Identifiant de l’utilisateur propriétaire', 'david');
    }

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = (string) $input->getOption('user');
        $owner = $this->em->getRepository(User::class)->findOneBy(['username' => $username]) ?? new User($username, ucfirst($username));
        $this->em->persist($owner);
        if ($this->em->getRepository(Suivi::class)->count(['owner' => $owner]) > 0) {
            $io->note('Des suivis existent déjà : rien à faire.');

            return Command::SUCCESS;
        }

        $today = new \DateTimeImmutable('today');
        $d = static fn (int $n): \DateTimeImmutable => $today->modify(sprintf('%+d days', $n));
        $rows = [
            // sujet, contact, sens, état, échéance, relance, réponse reçue, IA
            ['Planning des soutenances', 'Sophie Laurent', SuiviDirection::ToHandle, SuiviStatus::New, $d(2), null, true, false],
            ['Devis des affiches', 'Marc Dubois', SuiviDirection::Waiting, SuiviStatus::Waiting, $d(3), $d(-1), false, true],
            ['Salle B104 pour le jury', 'Scolarité', SuiviDirection::ToHandle, SuiviStatus::InProgress, $d(-3), null, false, false],
            ['Convention de stage', 'Claire Petit', SuiviDirection::Waiting, SuiviStatus::Waiting, $d(6), $d(3), false, false],
            ['Matériel de TP', 'Service achats', SuiviDirection::Waiting, SuiviStatus::Done, $d(-8), null, false, false],
        ];
        foreach ($rows as [$subject, $contact, $direction, $status, $due, $relance, $reply, $ai]) {
            $this->em->persist(
                (new Suivi())->setSubject($subject)->setContact($contact)->setDirection($direction)->setStatus($status)
                    ->setDueAt($due)->setRelanceAt($relance)->setReplyReceived($reply)->setSuggestedByAi($ai)
                    ->setOwner($owner)->setResponsible(ucfirst($username))->setMailId(self::MAIL_IDS[$subject] ?? null)
            );
        }
        $this->em->flush();
        $io->success(\count($rows).' suivis créés.');

        return Command::SUCCESS;
    }
}
