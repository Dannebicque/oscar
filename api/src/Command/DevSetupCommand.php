<?php

namespace App\Command;

use App\Entity\MailAccount;
use App\Entity\SharedMailbox;
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
            $this->em->persist($user);
            if ($this->em->getRepository(MailAccount::class)->count(['owner' => $user]) === 0) {
                $this->em->persist((new MailAccount())->setOwner($user)->setLabel($name.'@iut-troyes.fr (test)')->setAddress($name.'@iut-troyes.fr')
                    ->setImapHost('127.0.0.1')->setImapPort(1143)->setImapEncryption('none')
                    ->setSmtpHost('127.0.0.1')->setSmtpPort(1025)->setSmtpEncryption('none')->setUsername($name)
                    ->setPasswordEncrypted($this->vault->encrypt('oscar-test')));
            }
            $users[$name] = [$user, $role];
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
