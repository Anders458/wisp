<?php

namespace Wisp\Testing;

use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

abstract class TestCase extends WebTestCase
{
   protected ?KernelBrowser $client = null;
   protected ?Response $response = null;
   protected ?string $bearerToken = null;

   protected function setUp (): void
   {
      parent::setUp ();
      $this->client = static::createClient ();
   }

   protected function tearDown (): void
   {
      parent::tearDown ();
      $this->client = null;
      $this->response = null;
      $this->bearerToken = null;
   }

   protected function withToken (string $token): static
   {
      $this->bearerToken = $token;
      return $this;
   }

   protected function asUser (string $token): static
   {
      return $this->withToken ($token);
   }

   protected function get (string $uri, array $headers = []): static
   {
      return $this->request ('GET', $uri, [], $headers);
   }

   protected function post (string $uri, array $data = [], array $headers = []): static
   {
      return $this->json ('POST', $uri, $data, $headers);
   }

   protected function put (string $uri, array $data = [], array $headers = []): static
   {
      return $this->json ('PUT', $uri, $data, $headers);
   }

   protected function patch (string $uri, array $data = [], array $headers = []): static
   {
      return $this->json ('PATCH', $uri, $data, $headers);
   }

   protected function delete (string $uri, array $headers = []): static
   {
      return $this->request ('DELETE', $uri, [], $headers);
   }

   protected function json (string $method, string $uri, array $data = [], array $headers = []): static
   {
      $headers = array_merge ([
         'CONTENT_TYPE' => 'application/json',
         'HTTP_ACCEPT' => 'application/json'
      ], $headers);

      return $this->request ($method, $uri, $data, $headers, true);
   }

   protected function request (
      string $method,
      string $uri,
      array $data = [],
      array $headers = [],
      bool $jsonBody = false
   ): static
   {
      $server = $headers;

      if ($this->bearerToken !== null) {
         $server ['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->bearerToken;
         $this->bearerToken = null;
      }

      $content = $jsonBody && !empty ($data) ? json_encode ($data) : null;
      $parameters = $jsonBody ? [] : $data;

      $this->client->request ($method, $uri, $parameters, [], $server, $content);
      $this->response = $this->client->getResponse ();

      return $this;
   }

   protected function assertOk (): static
   {
      return $this->assertStatus (200);
   }

   protected function assertCreated (): static
   {
      return $this->assertStatus (201);
   }

   protected function assertNoContent (): static
   {
      return $this->assertStatus (204);
   }

   protected function assertBadRequest (): static
   {
      return $this->assertStatus (400);
   }

   protected function assertUnauthorized (): static
   {
      return $this->assertStatus (401);
   }

   protected function assertForbidden (): static
   {
      return $this->assertStatus (403);
   }

   protected function assertNotFound (): static
   {
      return $this->assertStatus (404);
   }

   protected function assertStatus (int $expected): static
   {
      $actual = $this->response->getStatusCode ();

      Assert::assertSame (
         $expected,
         $actual,
         "Expected status {$expected}, got {$actual}. Response: " . $this->response->getContent ()
      );

      return $this;
   }

   protected function assertJson (): static
   {
      $content = $this->response->getContent ();
      $decoded = json_decode ($content, true);

      Assert::assertNotNull (
         $decoded,
         "Response is not valid JSON: {$content}"
      );

      return $this;
   }

   protected function assertJsonPath (string $path, mixed $expected): static
   {
      $data = $this->toArray ();
      $value = $this->getValueByPath ($data, $path);

      Assert::assertSame (
         $expected,
         $value,
         "Expected [{$path}] to be " . json_encode ($expected) . ", got " . json_encode ($value)
      );

      return $this;
   }

   protected function assertJsonHas (string $path): static
   {
      $data = $this->toArray ();
      $parts = explode ('.', $path);
      $current = $data;

      foreach ($parts as $part) {
         if (!is_array ($current) || !array_key_exists ($part, $current)) {
            Assert::fail ("JSON path [{$path}] does not exist");
         }

         $current = $current [$part];
      }

      return $this;
   }

   protected function assertJsonMissing (string $path): static
   {
      $data = $this->toArray ();
      $parts = explode ('.', $path);
      $current = $data;

      foreach ($parts as $i => $part) {
         if (!is_array ($current)) {
            return $this;
         }

         if (!array_key_exists ($part, $current)) {
            return $this;
         }

         if ($i === count ($parts) - 1) {
            Assert::fail ("JSON path [{$path}] should not exist but does");
         }

         $current = $current [$part];
      }

      return $this;
   }

   protected function assertJsonCount (int $expected, ?string $path = null): static
   {
      $data = $this->toArray ();

      if ($path !== null) {
         $data = $this->getValueByPath ($data, $path);
      }

      Assert::assertIsArray ($data, "Value at path [{$path}] is not an array");
      Assert::assertCount ($expected, $data);

      return $this;
   }

   protected function toArray (): array
   {
      $content = $this->response->getContent ();
      $data = json_decode ($content, true);

      if (json_last_error () !== JSON_ERROR_NONE) {
         throw new \RuntimeException ('Response is not valid JSON: ' . $content);
      }

      return $data;
   }

   protected function getResponse (): Response
   {
      return $this->response;
   }

   protected function dump (): static
   {
      dump ($this->toArray ());
      return $this;
   }

   protected function dd (): never
   {
      dd ($this->toArray ());
   }

   private function getValueByPath (array $data, string $path): mixed
   {
      $parts = explode ('.', $path);
      $current = $data;

      foreach ($parts as $part) {
         if (!is_array ($current) || !array_key_exists ($part, $current)) {
            return null;
         }

         $current = $current [$part];
      }

      return $current;
   }
}
