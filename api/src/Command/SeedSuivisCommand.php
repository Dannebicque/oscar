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
        $this->addOption('history', null, InputOption::VALUE_NONE, 'Ajoute un historique de demandes envoyées (réponses et délais variés) pour essayer le bilan des suivis.');
    }

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = (string) $input->getOption('user');
        $owner = $this->em->getRepository(User::class)->findOneBy(['username' => $username]) ?? new User($username, ucfirst($username));
        $this->em->persist($owner);
        if ($input->getOption('history')) {
            return $this->history($owner, $io);
        }
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

    /** Historique pour le bilan : qui répond vite, qui répond lentement, qui ne répond pas. Sans effet si déjà inséré. */
    private function history(User $owner, SymfonyStyle $io): int
    {
        $marker = 'Historique : ';
        foreach ($this->em->getRepository(Suivi::class)->findBy(['owner' => $owner]) as $s) {
            if (str_starts_with($s->getSubject(), $marker)) {
                $io->note('L’historique existe déjà : rien à faire.');

                return Command::SUCCESS;
            }
        }
        $now = new \DateTimeImmutable();
        $me = $owner->getDisplayName();
        // contact, sujet, jours depuis l'envoi, heures avant la réponse (null = pas de réponse), relances, ouvert
        $rows = [
            ['Claire Petit', 'convention 2025', 40, 38, 1, false], ['Claire Petit', 'planning stagiaires', 22, 5, 0, false], ['Claire Petit', 'visite entreprise', 9, 3, 0, false],
            ['Scolarité', 'salle B12', 25, 5, 0, false], ['Scolarité', 'emploi du temps', 12, 30, 0, false], ['Scolarité', 'liste des inscrits', 3, null, 0, true],
            ['Marc Dubois', 'devis affiches v1', 60, 24 * 6, 2, false], ['Marc Dubois', 'devis affiches v2', 18, 24 * 4, 1, false], ['Marc Dubois', 'bon à tirer', 6, null, 1, true],
            ['Service achats', 'matériel de TP', 30, null, 2, true], ['Service achats', 'facture TP', 20, null, 1, true], ['Service achats', 'devis écrans', 9, null, 0, true],
            ['Sophie Laurent', 'programme des soutenances', 14, 3, 0, false], ['Lycée Camus', 'visite de l’IUT', 2, null, 0, true],
        ];
        foreach ($rows as [$contact, $subject, $daysAgo, $replyH, $relances, $open]) {
            $sent = $now->modify(sprintf('-%d days', $daysAgo));
            $s = (new Suivi())->setSubject($marker.$subject)->setContact($contact)->setDirection(SuiviDirection::Waiting)
                ->setStatus($open ? SuiviStatus::Waiting : SuiviStatus::Done)->setDueAt($sent->modify('+7 days')->setTime(0, 0))
                ->setOwner($owner)->setResponsible($me)->setRelanceCount($relances);
            $s->log($me, 'created', $sent);
            $s->log($me, 'sent', $sent);
            if ($replyH !== null) {
                $s->log($contact, 'replyReceived', $sent->modify(sprintf('+%d hours', $replyH)));
                $s->log($me, 'closed', $sent->modify(sprintf('+%d hours', $replyH + 20)));
            }
            $this->em->persist($s);
        }
        $this->em->flush();
        $io->success(\count($rows).' demandes d’historique créées.');

        return Command::SUCCESS;
    }
}
