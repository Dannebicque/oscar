<?php

namespace App\Security;

use App\Entity\ApiToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final class ApiTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $token = $this->em->getRepository(ApiToken::class)->findOneBy(['tokenHash' => ApiToken::hash($accessToken)]);
        if ($token === null || $token->isExpired()) {
            throw new BadCredentialsException('Session expirée ou révoquée.');
        }

        return new UserBadge($token->getUser()->getUserIdentifier());
    }
}
