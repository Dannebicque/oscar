<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:admin:create', description: 'Crée un administrateur d’instance, ou promeut un compte existant (installation sans interface, dépannage).')]
final class AdminCreateCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly UserPasswordHasherInterface $hasher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Adresse e-mail (identifiant de connexion)');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Nom affiché');
        $this->addOption('password', null, InputOption::VALUE_REQUIRED, 'Mot de passe (sinon demandé sans écho ; évite de l’écrire dans l’historique du shell)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string) $input->getArgument('email')));
        if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $io->error('Adresse e-mail invalide.');

            return Command::INVALID;
        }
        $repo = $this->em->getRepository(User::class);
        $user = $repo->findOneBy(['email' => $email]) ?? $repo->findOneBy(['username' => $email]);
        if ($user !== null && $user->isInstanceAdmin()) {
            $io->note('Ce compte est déjà administrateur.');

            return Command::SUCCESS;
        }
        if ($user === null || $user->getPassword() === null) {
            $password = (string) ($input->getOption('password') ?? $io->askHidden('Mot de passe (10 caractères au moins)') ?? '');
            if (mb_strlen($password) < 10) {
                $io->error('Le mot de passe doit faire au moins 10 caractères.');

                return Command::INVALID;
            }
        }
        $user ??= new User($email, (string) ($input->getOption('name') ?: ucfirst(strstr($email, '@', true) ?: $email)), $email);
        $user->setInstanceAdmin(true);
        if (isset($password)) {
            $user->setPasswordHash($this->hasher->hashPassword($user, $password));
        }
        $this->em->persist($user);
        $this->em->flush();
        $io->success(sprintf('%s est administrateur de l’instance.', $email));

        return Command::SUCCESS;
    }
}
