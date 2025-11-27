<?php

namespace Wisp\Middleware\Authentication;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as CurrentUserStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken as UserAuthContext;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Contracts\KeyValidatorInterface;

class KeyAuthentication
{
   public function __construct (
      private Request $request,
      private Response $response,
      private CurrentUserStorageInterface $currentUserStorage,
      private KeyValidatorInterface $validator,
      private string $header = 'X-API-Key',
      private ?string $query = null
   )
   {
   }

   public function before () : ?Response
   {
      $apiKey = $this->request->headers->get ($this->header);

      if (!$apiKey && $this->query) {
         $apiKey = $this->request->query->get ($this->query);
      }

      if (!$apiKey) {
         return null;
      }

      $user = $this->validator->validate ($apiKey);

      if (!$user) {
         return $this->response
            ->status (401)
            ->error ('Invalid API key');
      }

      $authContext = new UserAuthContext ($user, 'main', $user->getRoles ());
      $this->currentUserStorage->setToken ($authContext);

      return null;
   }
}
