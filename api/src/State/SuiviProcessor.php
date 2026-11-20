<?php

namespace App\State;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Suivi;
use App\Entity\User;
use App\Enum\SuiviStatus;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Écrit le journal du suivi (F-58) à partir de ce que l'utilisateur vient de changer. Jamais d'effet de bord sur la messagerie. */
final class SuiviProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: PersistProcessor::class)] private readonly ProcessorInterface $persist,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Suivi) {
            $author = ($u = $this->security->getUser()) instanceof User ? $u->getDisplayName() : '';
            $before = $context['previous_data'] ?? null;
            if (!$before instanceof Suivi) {
                $data->log($author, 'created');
            } else {
                foreach ($this->actions($before, $data) as $action) {
                    $data->log($author, $action);
                }
            }
        }

        return $this->persist->process($data, $operation, $uriVariables, $context);
    }

    /** @return list<string> */
    private function actions(Suivi $before, Suivi $after): array
    {
        $out = [];
        if ($before->getStatus() !== $after->getStatus()) {
            $out[] = $after->getStatus() === SuiviStatus::Done ? 'closed' : ($before->getStatus() === SuiviStatus::Done ? 'reopened' : 'status:'.$after->getStatus()->value);
        }
        if (!$before->isNoRelance() && $after->isNoRelance()) {
            $out[] = 'noRelance';
        } elseif ($before->isNoRelance() && !$after->isNoRelance()) {
            $out[] = 'relanceResumed';
        }
        if ($after->getRelanceCount() > $before->getRelanceCount()) {
            $out[] = 'relanceSent';
        } elseif ($before->getRelanceAt() != $after->getRelanceAt() && $after->getRelanceAt() !== null && !$after->isNoRelance()) {
            $out[] = 'snoozed';
        }
        if ($before->isReplyReceived() && !$after->isReplyReceived() && $after->getStatus() !== SuiviStatus::Done) {
            $out[] = $after->getStatus() === SuiviStatus::Waiting ? 'keptWaiting' : 'keptOpen';
        }

        return $out;
    }
}
