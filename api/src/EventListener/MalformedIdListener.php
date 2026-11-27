<?php

namespace App\EventListener;

use Doctrine\DBAL\Exception\DriverException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * PostgreSQL refuse un identifiant mal formé (SQLSTATE 22P02) : pour l'appelant, ce n'est pas une panne mais « inconnu » (F-79).
 * Un seul endroit, pour tous les points d'accès présents et à venir.
 */
#[AsEventListener(priority: 8)]
final class MalformedIdListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        for ($e = $event->getThrowable(); $e !== null; $e = $e->getPrevious()) {
            if ($e instanceof DriverException && $e->getSQLState() === '22P02' && str_contains($e->getMessage(), 'uuid')) {
                $event->setResponse(new JsonResponse(['title' => 'Je ne trouve pas cet élément.'], Response::HTTP_NOT_FOUND));

                return;
            }
        }
    }
}
