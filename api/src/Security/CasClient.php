<?php

namespace App\Security;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Validation d'un ticket CAS (protocole 2.0, serviceValidate). Connexion à l'application par CAS (N-05). */
final class CasClient
{
    public function __construct(private readonly HttpClientInterface $http, private readonly string $baseUrl)
    {
    }

    public function loginUrl(string $service): string
    {
        return rtrim($this->baseUrl, '/').'/login?'.http_build_query(['service' => $service]);
    }

    /** @return array{uid: string, name: string, email: ?string}|null null si le ticket est refusé */
    public function validate(string $ticket, string $service): ?array
    {
        $res = $this->http->request('GET', rtrim($this->baseUrl, '/').'/serviceValidate', ['query' => ['ticket' => $ticket, 'service' => $service]]);
        if ($res->getStatusCode() !== 200) {
            return null;
        }
        $xml = @simplexml_load_string($res->getContent(false), options: LIBXML_NONET | LIBXML_NOENT * 0);
        if ($xml === false) {
            return null;
        }
        $cas = $xml->children('http://www.yale.edu/tp/cas');
        $success = $cas->authenticationSuccess ?? null;
        if ($success === null || !isset($success->user) || trim((string) $success->user) === '') {
            return null;
        }
        $attrs = $success->attributes ?? null;
        $get = static fn (string $k): ?string => $attrs !== null && isset($attrs->{$k}) && trim((string) $attrs->{$k}) !== '' ? trim((string) $attrs->{$k}) : null;
        $uid = trim((string) $success->user);

        return ['uid' => $uid, 'name' => $get('displayName') ?? $get('cn') ?? $uid, 'email' => $get('mail')];
    }
}
