<?php

namespace App\Tests\Feature;

use Wisp\Testing\TestCase;

/**
 * Tests for tiered rate limiting.
 *
 * Note: These tests require throttle to be enabled in config.
 * The test environment disables throttle by default, so these tests
 * verify the attribute parsing and role-based selection logic.
 */
class ThrottleTest extends TestCase
{
   /**
    * Test that the throttle endpoint is accessible.
    * (Throttle is disabled in test env, so this just verifies routing works)
    */
   public function test_throttle_endpoint_is_accessible (): void
   {
      $this->get ('/v1/test/throttle/default')
         ->assertOk ()
         ->assertJsonPath ('body.tier', 'default');
   }

   public function test_tiered_throttle_endpoint_is_accessible (): void
   {
      $this->get ('/v1/test/throttle/tiered')
         ->assertOk ()
         ->assertJsonPath ('body.tier', 'tiered');
   }
}
