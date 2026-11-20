<?php

namespace App\Tests\Security;

use App\Security\CasClient;
use App\Security\CredentialVault;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CasClientTest extends TestCase
{
    private const OK = '<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas"><cas:authenticationSuccess><cas:user>dannebic</cas:user><cas:attributes><cas:displayName>David A.</cas:displayName><cas:mail>d@iut.fr</cas:mail></cas:attributes></cas:authenticationSuccess></cas:serviceResponse>';
    private const KO = '<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas"><cas:authenticationFailure code="INVALID_TICKET">non</cas:authenticationFailure></cas:serviceResponse>';

    private static function cas(string $xml, int $status = 200): CasClient
    {
        return new CasClient(new MockHttpClient(new MockResponse($xml, ['http_code' => $status])), 'https://cas.test/cas');
    }

    public function testValidTicketGivesIdentity(): void
    {
        self::assertSame(['uid' => 'dannebic', 'name' => 'David A.', 'email' => 'd@iut.fr'], self::cas(self::OK)->validate('ST-1', 'https://api/cb'));
    }

    public function testRefusedMalformedOrFailedTicketsGiveNothing(): void
    {
        self::assertNull(self::cas(self::KO)->validate('ST-1', 's'));
        self::assertNull(self::cas('pas du xml')->validate('ST-1', 's'));
        self::assertNull(self::cas(self::OK, 500)->validate('ST-1', 's'));
        self::assertNull(self::cas('<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas"><cas:authenticationSuccess><cas:user> </cas:user></cas:authenticationSuccess></cas:serviceResponse>')->validate('ST-1', 's'));
    }

    public function testLoginUrlCarriesTheService(): void
    {
        self::assertSame('https://cas.test/cas/login?service=https%3A%2F%2Fapi%2Fcb', self::cas(self::OK)->loginUrl('https://api/cb'));
    }

    public function testVaultRoundTripAndTamperDetection(): void
    {
        $v = new CredentialVault('base64:'.base64_encode(str_repeat('k', 32)));
        $enc = $v->encrypt('mot-de-passe');
        self::assertStringNotContainsString('mot-de-passe', $enc);
        self::assertSame('mot-de-passe', $v->decrypt($enc));
        self::assertNotSame($enc, $v->encrypt('mot-de-passe'), 'nonce différent à chaque chiffrement');
        $this->expectException(\RuntimeException::class);
        (new CredentialVault('base64:'.base64_encode(str_repeat('x', 32))))->decrypt($enc);
    }

    public function testVaultRejectsABadKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CredentialVault('trop-court');
    }
}
