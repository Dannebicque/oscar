<?php

namespace App\Controller;

use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Entity\User;
use App\Mail\MailSynchronizer;
use App\Mail\MailboxReaderFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * « Enregistrer le brouillon » (F-16) : le brouillon est déposé dans le dossier Drafts du serveur IMAP (il suit l'utilisateur dans
 * son autre webmail), avec ses pièces jointes, ses copies et l'identité d'envoi. Rien n'est envoyé. Un brouillon modifié remplace l'ancien, qui part à la corbeille (jamais supprimé).
 */
final class SaveDraftController extends AbstractController
{
    #[Route('/api/mails/draft', methods: ['POST'])]
    public function __invoke(Request $request, #[CurrentUser] User $user, EntityManagerInterface $em, MailboxReaderFactory $factory, MailSynchronizer $sync, \Psr\Log\LoggerInterface $logger, \App\Mail\IdentityResolver $resolver, \App\Instance\InstanceConfig $instance): JsonResponse
    {
        $in = json_decode($request->getContent(), true) ?: [];
        $account = $em->find(MailAccount::class, (string) ($in['accountId'] ?? ''));
        if ($account === null || $account->getOwner()?->getId() !== $user->getId()) {
            return new JsonResponse(['title' => 'Compte de messagerie inconnu.'], Response::HTTP_NOT_FOUND);
        }
        $valid = static fn ($list) => array_values(array_filter(\array_slice((array) $list, 0, 50), static fn ($a) => \is_string($a) && filter_var($a, FILTER_VALIDATE_EMAIL)));
        try {
            $identity = $resolver->resolve($user, $account, (string) ($in['identityId'] ?? ''));
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['title' => 'Cette identité d’expéditeur n’existe pas pour ce compte.', 'field' => 'identityId'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        // Pièces jointes : même limite que l'envoi (celle du compte), vérifiée avant de toucher au serveur.
        $files = [];
        $total = 0;
        foreach (\array_slice((array) ($in['attachments'] ?? []), 0, 10) as $a) {
            $raw = base64_decode((string) ($a['content'] ?? ''), true);
            if ($raw === false) {
                return new JsonResponse(['title' => 'Pièce jointe illisible.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $total += \strlen($raw);
            $files[] = [$raw, mb_substr((string) ($a['name'] ?? 'pièce-jointe'), 0, 150)];
        }
        if ($total > $account->attachmentLimitBytes($instance->defaultAttachmentMb())) {
            return new JsonResponse(['title' => sprintf('Les pièces jointes dépassent %d Mo, la limite de ce compte. Retire-en une ou envoie un lien.', $account->attachmentLimitBytes($instance->defaultAttachmentMb()) / 1048576), 'limitMb' => (int) ($account->attachmentLimitBytes($instance->defaultAttachmentMb()) / 1048576)], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }
        $headers = new Headers();
        $headers->addMailboxListHeader('From', [$identity !== null ? new Address($identity->address, $identity->name) : new Address($account->getAddress(), $user->getDisplayName())]);
        if ($identity?->replyTo !== null) {
            $headers->addMailboxListHeader('Reply-To', [new Address($identity->replyTo)]);
        }
        if (($to = $valid($in['to'] ?? [])) !== []) {
            $headers->addMailboxListHeader('To', array_map(static fn ($a) => new Address($a), $to));
        }
        if (($cc = $valid($in['cc'] ?? [])) !== []) {
            $headers->addMailboxListHeader('Cc', array_map(static fn ($a) => new Address($a), $cc));
        }
        if (($bcc = $valid($in['bcc'] ?? [])) !== []) {
            $headers->addMailboxListHeader('Bcc', array_map(static fn ($a) => new Address($a), $bcc));
        }
        $headers->addTextHeader('Subject', mb_substr((string) ($in['subject'] ?? ''), 0, 500));
        $headers->addDateHeader('Date', new \DateTimeImmutable());
        $headers->addIdHeader('Message-ID', bin2hex(random_bytes(12)).'@'.(substr(strrchr($account->getAddress(), '@') ?: '@localhost', 1)));
        if (($rid = $in['inReplyToMailId'] ?? null) && ($orig = $em->find(Mail::class, (string) $rid)) && $orig->getOwner()?->getId() === $user->getId()) {
            $headers->addTextHeader('In-Reply-To', $orig->getMessageId());
        }
        $text = new TextPart(mb_substr((string) ($in['body'] ?? ''), 0, 200000));
        $body = $files === [] ? $text : new MixedPart($text, ...array_map(static fn (array $f) => new DataPart($f[0], $f[1], 'application/octet-stream'), $files));
        // Message::toString() retire le Cci (pour ne jamais le divulguer à l'envoi) ; un brouillon, lui, doit le garder pour être repris tel quel.
        $prepared = (new Message($headers, $body))->getPreparedHeaders();
        if ($bcc !== []) {
            $prepared->addMailboxListHeader('Bcc', array_map(static fn ($a) => new Address($a), $bcc));
        }
        $raw = $prepared->toString().$body->toString();

        try {
            $reader = $factory->create($account);
            $reader->append('Drafts', $raw, ['\\Draft', '\\Seen']);
            try {
                if (($in['replacesMailId'] ?? '') !== '' && ($old = $em->find(Mail::class, (string) $in['replacesMailId'])) !== null && $old->getOwner()?->getId() === $user->getId() && $old->getFolder() === 'Drafts') {
                    $uid = $reader->move('Drafts', $old->getUid(), $old->getMessageId(), 'Trash');
                    $uid !== null ? $old->relocate('Trash', $uid) : $em->remove($old);
                }
            } catch (\Throwable $e) {
                $logger->warning('Ancien brouillon non remplacé : '.$e->getMessage()); // le nouveau brouillon est enregistré : on ne perd rien
            }
            $sync->sync($account, $reader);
        } catch (\Throwable $e) {
            $logger->warning('Brouillon non enregistré : '.$e->getMessage());

            return new JsonResponse(['title' => 'Le brouillon n’a pas pu être enregistré : le serveur de messagerie ne répond pas. Ton texte est toujours là, réessaie dans un instant.', 'detail' => $this->getParameter('kernel.debug') ? $e->getMessage() : null], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['saved' => true], Response::HTTP_CREATED);
    }
}
