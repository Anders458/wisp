<?php

namespace App\Tests\Feature;

use Wisp\Testing\TestCase;

class CacheTest extends TestCase
{
   public function test_cached_endpoint_has_cache_headers (): void
   {
      $response = $this->get ('/v1/products/1');

      $response->assertOk ();

      $cacheControl = $response->getHeader ('Cache-Control');
      $this->assertStringContainsString ('max-age=300', $cacheControl);
      $this->assertStringContainsString ('private', $cacheControl);
      $this->assertNotNull ($response->getHeader ('ETag'));
   }

   public function test_featured_returns_products (): void
   {
      $this->get ('/v1/products/featured')
         ->assertOk ()
         ->assertJsonCount (3, 'body');
   }

   public function test_vary_header_applied (): void
   {
      $response = $this->get ('/v1/products');

      $response->assertOk ();

      $vary = $response->getHeader ('Vary');
      $this->assertStringContainsString ('Accept', $vary);
   }

   public function test_post_request_uses_default_cache (): void
   {
      $response = $this->post ('/v1/products', [
         'name' => 'New Product',
         'price' => 12.99
      ]);

      $response->assertCreated ();

      // POST uses Symfony default cache (no-cache or must-revalidate)
      $cacheControl = $response->getHeader ('Cache-Control');
      $this->assertStringNotContainsString ('max-age=300', $cacheControl);
   }

   public function test_error_response_uses_default_cache (): void
   {
      $response = $this->get ('/v1/products/999');

      $response->assertNotFound ();

      // Error responses use Symfony default cache
      $cacheControl = $response->getHeader ('Cache-Control');
      $this->assertStringNotContainsString ('max-age=300', $cacheControl);
   }

   public function test_etag_is_set (): void
   {
      $response = $this->get ('/v1/products/1');

      $response->assertOk ();
      $this->assertNotNull ($response->getHeader ('ETag'));
      $this->assertMatchesRegularExpression ('/^"[a-f0-9]{32}"$/', $response->getHeader ('ETag'));
   }

   public function test_list_endpoint_cached (): void
   {
      $response = $this->get ('/v1/products');

      $response->assertOk ();

      $cacheControl = $response->getHeader ('Cache-Control');
      $this->assertStringContainsString ('max-age=60', $cacheControl);
   }
}
