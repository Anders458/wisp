<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiTokenAuthenticator extends AbstractAuthenticator
{
   /**
    * Demo tokens - in production, use a database or external auth service.
    */
   private const TOKENS = [
      'user-token-123' => [
         'id' => '1',
         'email' => 'user@example.com',
         'roles' => [ 'ROLE_USER' ]
      ],
      'admin-token-456' => [
         'id' => '2',
         'email' => 'admin@example.com',
         'roles' => [ 'ROLE_USER', 'ROLE_ADMIN' ]
      ]
   ];

   public function supports (Request $request): ?bool
   {
      return $request->headers->has ('Authorization');
   }

   public function authenticate (Request $request): Passport
   {
      $authHeader = $request->headers->get ('Authorization');

      if (!$authHeader || !str_starts_with ($authHeader, 'Bearer ')) {
         throw new CustomUserMessageAuthenticationException ('Invalid authorization header');
      }

      $token = substr ($authHeader, 7);

      if (!isset (self::TOKENS [$token])) {
         throw new CustomUserMessageAuthenticationException ('Invalid API token');
      }

      $userData = self::TOKENS [$token];

      return new SelfValidatingPassport (
         new UserBadge ($userData ['email'], function () use ($userData) {
            return new User (
               $userData ['id'],
               $userData ['email'],
               '',
               $userData ['roles']
            );
         })
      );
   }

   public function onAuthenticationSuccess (Request $request, TokenInterface $token, string $firewallName): ?Response
   {
      return null;
   }

   public function onAuthenticationFailure (Request $request, AuthenticationException $exception): ?Response
   {
      // Return null to let ExceptionSubscriber handle via Flash
      return null;
   }
}
