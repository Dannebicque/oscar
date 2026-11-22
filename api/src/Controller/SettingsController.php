<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Réglages de l'utilisateur : relances, récaps, mode d'IA (F-54, F-63, F-01, §5). Valeurs validées, jamais prises telles quelles. */
final class SettingsController extends AbstractController
{
    private const RULES = [
        'relanceHours' => [24, 48, 72, 168], 'dailyCap' => [3, 5, 10], 'meetingLeadMinutes' => [0, 5, 10, 15, 30], 'aiMode' => ['university', 'external', 'off'], 'locale' => ['fr', 'en'],
    ];
    private const BOOLS = ['businessDays', 'recapEmail', 'recapHome', 'includePersonalCalendar', 'shareBusy', 'aiSuggestFollow', 'aiSuggestTasks', 'aiDraftRelance', 'dirShowDepartment', 'dirShowPhone'];
    private const TIMES = ['recapMorning', 'recapEvening'];

    #[Route('/api/settings', methods: ['GET'])]
    public function show(#[CurrentUser] User $user, EntityManagerInterface $em): JsonResponse
    {
        return new JsonResponse(($em->getRepository(UserSettings::class)->findOneBy(['owner' => $user]) ?? new UserSettings($user))->all());
    }

    #[Route('/api/settings', methods: ['PUT', 'PATCH'])]
    public function update(Request $r, #[CurrentUser] User $user, EntityManagerInterface $em): JsonResponse
    {
        $in = $r->toArray();
        $changes = [];
        foreach ($in as $k => $v) {
            if (isset(self::RULES[$k])) {
                if (!\in_array($v, self::RULES[$k], true)) {
                    return $this->invalid($k);
                }
            } elseif (\in_array($k, self::BOOLS, true)) {
                if (!\is_bool($v)) {
                    return $this->invalid($k);
                }
            } elseif ($k === 'notifyChannels') {
                if (!\is_array($v)) {
                    return $this->invalid($k);
                }
                $clean = [];
                foreach (\App\Notification\NotificationCenter::TYPES as $type) {
                    foreach (\App\Notification\NotificationCenter::CHANNELS as $ch) {
                        if (isset($v[$type][$ch])) {
                            if (!\is_bool($v[$type][$ch])) {
                                return $this->invalid($k);
                            }
                            $clean[$type][$ch] = $v[$type][$ch];
                        }
                    }
                }
                $v = $clean;
            } elseif ($k === 'timezone') {
                if (!\is_string($v) || !\in_array($v, \DateTimeZone::listIdentifiers(), true)) {
                    return $this->invalid($k);
                }
            } elseif ($k === 'vacationFrom' || $k === 'vacationTo') {
                if (!\is_string($v) || ($v !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) || !checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4))))) {
                    return $this->invalid($k);
                }
            } elseif ($k === 'vacationMessage') {
                if (!\is_string($v) || mb_strlen($v) > 2000) {
                    return $this->invalid($k);
                }
            } elseif (\in_array($k, self::TIMES, true)) {
                if (!\is_string($v) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v)) {
                    return $this->invalid($k);
                }
            } else {
                continue; // champ inconnu : ignoré
            }
            $changes[$k] = $v;
        }
        $settings = $em->getRepository(UserSettings::class)->findOneBy(['owner' => $user]) ?? new UserSettings($user);
        $merged = $changes + $settings->all();
        if (($merged['vacationFrom'] ?? '') !== '' && ($merged['vacationTo'] ?? '') !== '' && $merged['vacationTo'] < $merged['vacationFrom']) {
            return $this->invalid('vacationTo');
        }
        $settings->apply($changes);
        $em->persist($settings);
        $em->flush();

        return new JsonResponse($settings->all());
    }

    private function invalid(string $field): JsonResponse
    {
        return new JsonResponse(['title' => "Valeur invalide pour « $field »."], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
