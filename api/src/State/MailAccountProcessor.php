<?php

namespace App\State;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\MailAccount;
use App\Security\CredentialVault;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Chiffre le mot de passe reçu avant de persister : il n'est jamais stocké ni renvoyé en clair. */
final class MailAccountProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: PersistProcessor::class)] private readonly ProcessorInterface $persist,
        private readonly CredentialVault $vault,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof MailAccount && $data->getPassword() !== null && $data->getPassword() !== '') {
            $data->setPasswordEncrypted($this->vault->encrypt($data->getPassword()));
            $data->setPassword(null);
        }

        return $this->persist->process($data, $operation, $uriVariables, $context);
    }
}
