<?php

namespace App\Controller;

use App\Mail\HostGuard;
use App\Mail\WebklexMailboxReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Teste des serveurs IMAP et SMTP AVANT d'enregistrer le compte (assistant de connexion). Rien n'est enregistré, le mot de passe n'est ni conservé ni journalisé.
 * Chaque serveur est testé séparément pour dire lequel pose problème.
 */
final class MailAccountProbeController extends AbstractController
{
    public function __construct(private readonly HostGuard $guard)
    {
    }

    #[Route('/api/mail-accounts/probe', methods: ['POST'])]
    public function __invoke(Request $r): JsonResponse
    {
        $in = $r->toArray();
        $enc = static fn ($v) => \in_array($v, ['none', 'ssl', 'tls'], true) ? $v : 'ssl';
        $imap = ['host' => trim((string) ($in['imapHost'] ?? '')), 'port' => (int) ($in['imapPort'] ?? 993), 'enc' => $enc($in['imapEncryption'] ?? 'ssl')];
        $smtp = ['host' => trim((string) ($in['smtpHost'] ?? '')), 'port' => (int) ($in['smtpPort'] ?? 587), 'enc' => $enc($in['smtpEncryption'] ?? 'tls')];
        $user = (string) ($in['username'] ?? '');
        $pass = (string) ($in['password'] ?? '');
        if ($imap['host'] === '' || $smtp['host'] === '' || $user === '' || $pass === '') {
            return new JsonResponse(['title' => 'Il manque un serveur, un identifiant ou un mot de passe.'], 422);
        }

        return new JsonResponse([
            'imap' => $this->imap($imap, $user, $pass),
            'smtp' => $this->smtp($smtp, $user, $pass),
        ]);
    }

    /** @return array{ok: bool, message: string} */
    private function imap(array $s, string $user, string $pass): array
    {
        if ($refus = $this->guard->refuse($s['host'], $s['port'])) {
            return ['ok' => false, 'message' => $refus];
        }
        try {
            (new WebklexMailboxReader($s['host'], $s['port'], $user, $pass, $s['enc'], $s['enc'] !== 'none'))->fetchAfter('INBOX', PHP_INT_MAX - 1);

            return ['ok' => true, 'message' => 'Réception : connexion réussie.'];
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'Réception : connexion impossible. Vérifie l’adresse du serveur, le port, le chiffrement et le mot de passe.'];
        }
    }

    /** @return array{ok: bool, message: string} */
    private function smtp(array $s, string $user, string $pass): array
    {
        if ($refus = $this->guard->refuse($s['host'], $s['port'])) {
            return ['ok' => false, 'message' => $refus];
        }
        $scheme = $s['enc'] === 'ssl' ? 'smtps' : 'smtp';
        $opts = $s['enc'] === 'none' ? '?auto_tls=false&verify_peer=0' : '';
        try {
            $t = Transport::fromDsn(sprintf('%s://%s:%s@%s:%d%s', $scheme, rawurlencode($user), rawurlencode($pass), $s['host'], $s['port'], $opts));
            $t instanceof \Symfony\Component\Mailer\Transport\Smtp\SmtpTransport ? $t->start() : null;

            return ['ok' => true, 'message' => 'Envoi : connexion réussie.'];
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'Envoi : connexion impossible. Vérifie l’adresse du serveur, le port, le chiffrement et le mot de passe.'];
        }
    }
}
