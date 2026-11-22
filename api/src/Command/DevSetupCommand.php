<?php

namespace App\Command;

use App\Calendar\CalendarSynchronizer;
use App\Entity\CalendarAccount;
use App\Entity\MailAccount;
use App\Entity\SharedMailbox;
use App\Entity\Task;
use App\Enum\TaskOrigin;
use App\Entity\User;
use App\Security\CredentialVault;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Prépare un utilisateur de développement relié au Dovecot de test (docker/dovecot). Refuse de tourner en production. */
#[AsCommand(name: 'app:dev:setup', description: 'Crée les utilisateurs de test (david, claire, marc, lea), leurs comptes Dovecot et l’alias contact@.')]
final class DevSetupCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CredentialVault $vault,
        private readonly CalendarSynchronizer $calendars,
        #[Autowire('%kernel.environment%')] private readonly string $env,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!\in_array($this->env, ['dev', 'test'], true)) {
            $io->error('Commande réservée au développement.');

            return Command::FAILURE;
        }
        $users = [];
        foreach (['david' => 'admin', 'claire' => 'member', 'marc' => 'member', 'lea' => 'member'] as $name => $role) {
            $user = $this->em->getRepository(User::class)->findOneBy(['username' => $name]) ?? new User($name, ucfirst($name), $name.'@iut-troyes.fr');
            // En développement, david administre l'instance : l'assistant d'installation ne s'ouvre pas devant les comptes de test.
            $user->setInstanceAdmin($name === 'david');
            $this->em->persist($user);
            if ($this->em->getRepository(MailAccount::class)->count(['owner' => $user]) === 0) {
                $this->em->persist((new MailAccount())->setOwner($user)->setLabel($name.'@iut-troyes.fr (test)')->setAddress($name.'@iut-troyes.fr')
                    ->setImapHost('127.0.0.1')->setImapPort(1143)->setImapEncryption('none')
                    ->setSmtpHost('127.0.0.1')->setSmtpPort(1025)->setSmtpEncryption('none')->setUsername($name)
                    ->setPasswordEncrypted($this->vault->encrypt('oscar-test')));
            }
            $users[$name] = [$user, $role];
        }
        // Quelques tâches pour que la to-do ne soit pas vide au premier lancement.
        $david = $users['david'][0];
        if ($this->em->getRepository(Task::class)->count(['owner' => $david]) === 0) {
            $d = static fn (int $n): \DateTimeImmutable => new \DateTimeImmutable("today $n days");
            foreach ([
                ['Réserver les salles de soutenance', 2, 7, 'Jury de janvier', ['salles'], TaskOrigin::Manual, false, null],
                ['Relire la convention de Claire Petit', 1, 0, 'Stages', ['juridique'], TaskOrigin::Mail, false, 'Convention de stage'],
                ['Confirmer le nombre de participants', 2, 4, 'Jury de janvier', [], TaskOrigin::Ai, true, 'Planning des soutenances'],
                ['Préparer le support de la réunion pédagogique', 3, -1, 'Pédagogie', ['réunion'], TaskOrigin::Event, false, 'Réunion pédagogique'],
                ['Commander le papier A2', 4, null, null, [], TaskOrigin::Manual, false, null],
            ] as [$title, $prio, $due, $project, $tags, $origin, $confirm, $source]) {
                $this->em->persist((new Task())->setOwner($david)->setTitle($title)->setPriority($prio)->setDueAt($due === null ? null : $d($due))
                    ->setProject($project)->setTags($tags)->setOrigin($origin)->setToConfirm($confirm)->setSource($source));
            }
        }
        // Deux agendas de démonstration (pro et perso) : événements recalculés autour d'aujourd'hui, sans serveur CalDAV.
        foreach ([['IUT · CalDAV', 'caldav', 'demo://iut', false], ['Perso · Google Agenda', 'google', 'demo://perso', true]] as [$label, $kind, $url, $personal]) {
            if ($this->em->getRepository(CalendarAccount::class)->findOneBy(['owner' => $david, 'url' => $url]) === null) {
                $this->em->persist((new CalendarAccount())->setOwner($david)->setLabel($label)->setKind($kind)->setUrl($url)->setUsername('demo')->setPersonal($personal));
            }
        }
        $this->em->flush();
        foreach ($this->em->getRepository(CalendarAccount::class)->findBy(['owner' => $david]) as $cal) {
            if (str_starts_with($cal->getUrl(), 'demo://')) {
                $this->calendars->sync($cal);
            }
        }
        // L'alias contact@ servi par les quatre comptes de test (F-44, F-67).
        $box = $this->em->getRepository(SharedMailbox::class)->findOneBy(['slug' => 'contact']) ?? new SharedMailbox('contact@iut-troyes.fr', 'Contact', 'contact');
        foreach ($users as [$user, $role]) {
            $box->addMember($user, $role);
        }
        $this->em->persist($box);
        $this->em->flush();
        $io->success('Utilisateurs david, claire, marc, lea, leurs comptes Dovecot de test et l’alias contact@ sont prêts. Connexion de dev : POST /api/auth/dev-login.');

        return Command::SUCCESS;
    }
}
