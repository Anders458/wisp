<?php

namespace Wisp\Security\OAuth;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Github;
use League\OAuth2\Client\Provider\Google;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class OAuthManager
{
   private array $providers = [];

   public function __construct (
      private SessionInterface $session,
      array $providerConfigs = []
   )
   {
      foreach ($providerConfigs as $name => $config) {
         $this->registerProvider ($name, $config);
      }
   }

   /**
    * Get OAuth provider by name
    *
    * @param string $name Provider name (e.g., 'github', 'google')
    * @return AbstractProvider
    * @throws \RuntimeException If provider not configured
    */
   public function getProvider (string $name) : AbstractProvider
   {
      if (!isset ($this->providers [$name])) {
         throw new \RuntimeException ("OAuth provider '{$name}' is not configured");
      }

      return $this->providers [$name];
   }

   /**
    * Register an OAuth provider
    *
    * @param string $name Provider name
    * @param array $config Provider configuration
    */
   private function registerProvider (string $name, array $config) : void
   {
      $provider = match (strtolower ($name)) {
         'github' => new Github ([
            'clientId'     => $config ['client_id'],
            'clientSecret' => $config ['client_secret'],
            'redirectUri'  => $config ['redirect_uri']
         ]),
         'google' => new Google ([
            'clientId'     => $config ['client_id'],
            'clientSecret' => $config ['client_secret'],
            'redirectUri'  => $config ['redirect_uri']
         ]),
         default => throw new \RuntimeException ("Unsupported OAuth provider: {$name}")
      };

      $this->providers [$name] = $provider;
   }
}
