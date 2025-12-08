<?php

namespace App\Tests\Feature;

use Wisp\Testing\TestCase;

class PaginationTest extends TestCase
{
   public function test_offset_pagination_default (): void
   {
      $this->get ('/v1/products')
         ->assertOk ()
         ->assertJsonPath ('pagination.mode', 'offset')
         ->assertJsonPath ('pagination.page', 1)
         ->assertJsonPath ('pagination.limit', 5)
         ->assertJsonPath ('pagination.total', 10)
         ->assertJsonPath ('pagination.total_pages', 2)
         ->assertJsonPath ('pagination.has_more', true)
         ->assertJsonCount (5, 'body');
   }

   public function test_offset_pagination_page_2 (): void
   {
      $this->get ('/v1/products?page=2&limit=5')
         ->assertOk ()
         ->assertJsonPath ('pagination.mode', 'offset')
         ->assertJsonPath ('pagination.page', 2)
         ->assertJsonPath ('pagination.has_more', false)
         ->assertJsonCount (5, 'body');
   }

   public function test_offset_pagination_custom_limit (): void
   {
      $this->get ('/v1/products?page=1&limit=3')
         ->assertOk ()
         ->assertJsonPath ('pagination.limit', 3)
         ->assertJsonPath ('pagination.total_pages', 4)
         ->assertJsonCount (3, 'body');
   }

   public function test_cursor_pagination (): void
   {
      $this->get ('/v1/products?cursor=3&limit=3')
         ->assertOk ()
         ->assertJsonPath ('pagination.mode', 'cursor')
         ->assertJsonPath ('pagination.cursor', '3')
         ->assertJsonPath ('pagination.limit', 3)
         ->assertJsonHas ('pagination.next_cursor')
         ->assertJsonPath ('pagination.has_more', true)
         ->assertJsonCount (3, 'body');
   }

   public function test_cursor_pagination_last_page (): void
   {
      $this->get ('/v1/products?cursor=8&limit=5')
         ->assertOk ()
         ->assertJsonPath ('pagination.mode', 'cursor')
         ->assertJsonPath ('pagination.has_more', false)
         ->assertJsonCount (2, 'body');
   }

   public function test_cursor_pagination_from_start (): void
   {
      // No cursor = start from beginning
      $this->get ('/v1/products?cursor=&limit=3')
         ->assertOk ()
         ->assertJsonPath ('pagination.mode', 'cursor')
         ->assertJsonCount (3, 'body');
   }

   public function test_limit_clamped_to_max (): void
   {
      $this->get ('/v1/products?limit=500')
         ->assertOk ()
         ->assertJsonPath ('pagination.limit', 100);
   }

   public function test_envelope_includes_pagination (): void
   {
      $json = $this->get ('/v1/products')->toArray ();

      $this->assertArrayHasKey ('pagination', $json);
      $this->assertArrayHasKey ('body', $json);
      $this->assertArrayHasKey ('mode', $json ['pagination']);
   }
}
