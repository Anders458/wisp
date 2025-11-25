<?php

namespace Wisp\Testing;

use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\Response as BrowserKitResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Wisp\Wisp;

class WispTestCase extends AbstractBrowser
{
   public Wisp|HttpKernelInterface $app;

   public function __construct (Wisp|HttpKernelInterface $app)
   {
      $this->app = $app;
      parent::__construct ([], new History (), new CookieJar ());
   }

   protected function doRequest (object $request): BrowserKitResponse
   {
      $wispRequest = \Wisp\Http\Request::create (
         $request->getUri (),
         $request->getMethod (),
         $request->getParameters (),
         $request->getCookies (),
         $request->getFiles (),
         $request->getServer (),
         $request->getContent ()
      );

      if ($this->app instanceof Wisp) {
         $response = $this->app->handleRequest ($wispRequest);
      } else {
         $response = $this->app->handle ($wispRequest);
      }

      return new BrowserKitResponse (
         $response->getContent (),
         $response->getStatusCode (),
         $response->headers->all ()
      );
   }

   public function assertJson (): self
   {
      $content = $this->getResponse ()->getContent ();
      json_decode ($content, true);

      if (json_last_error () !== JSON_ERROR_NONE) {
         throw new \RuntimeException ('Response is not valid JSON: ' . $content);
      }

      return $this;
   }

   public function assertOk (): self
   {
      return $this->assertStatus (200);
   }

   public function assertStatus (int $expected): self
   {
      $actual = $this->getResponse ()->getStatusCode ();

      if ($actual !== $expected) {
         throw new \RuntimeException ("Expected status {$expected}, got {$actual}");
      }

      return $this;
   }

   public function delete (string $uri, array $headers = []): self
   {
      $this->request ('DELETE', $uri, [], [], $headers);
      return $this;
   }

   public function get (string $uri, array $headers = []): self
   {
      $this->request ('GET', $uri, [], [], $headers);
      return $this;
   }

   public function json (string $method, string $uri, array $data = [], array $headers = []): self
   {
      $server = array_merge (
         [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
         ],
         $headers
      );

      $this->request (
         $method,
         $uri,
         [],
         [],
         $server,
         json_encode ($data)
      );

      return $this;
   }

   public function patch (string $uri, array $data = [], array $headers = []): self
   {
      return $this->json ('PATCH', $uri, $data, $headers);
   }

   public function post (string $uri, array $data = [], array $headers = []): self
   {
      return $this->json ('POST', $uri, $data, $headers);
   }

   public function put (string $uri, array $data = [], array $headers = []): self
   {
      return $this->json ('PUT', $uri, $data, $headers);
   }

   public function toArray (): array
   {
      $content = $this->getResponse ()->getContent ();
      $data = json_decode ($content, true);

      if (json_last_error () !== JSON_ERROR_NONE) {
         throw new \RuntimeException ('Response is not valid JSON: ' . $content);
      }

      return $data;
   }
}
