<?php

namespace App\Tests\Feature;

use Wisp\Testing\TestCase;

class NewFeaturesTest extends TestCase
{
   // === API Versioning Tests ===
   // Note: The #[Version] attribute requires route loader integration
   // which is complex in Symfony. These tests are skipped for now.
   // The feature works by having the base controller define the version,
   // and child controllers inherit it. Routes would be: /v2/status, etc.

   // === Before/After Hook Tests ===

   public function test_before_hook_runs_before_action (): void
   {
      $this->get ('/v1/test/hooks')
         ->assertOk ()
         ->assertJsonPath ('body.before_hook_ran', true);
   }

   public function test_after_hook_adds_response_header (): void
   {
      $response = $this->get ('/v1/test/hooks')
         ->assertOk ()
         ->getResponse ();

      $this->assertNotNull ($response->headers->get ('X-Hook-After'));
      $this->assertEquals ('executed', $response->headers->get ('X-Hook-After'));
   }

   public function test_before_hook_with_only_scope (): void
   {
      // This endpoint has a before hook with only: ['maintenance']
      // When X-Force-Maintenance header is set, it should return 503
      $this->request ('GET', '/v1/test/maintenance', [], [
         'HTTP_X_FORCE_MAINTENANCE' => 'true'
      ])
         ->assertStatus (503)
         ->assertJsonPath ('body.message', 'Service under maintenance');
   }

   public function test_before_hook_with_only_scope_does_not_apply_to_other_actions (): void
   {
      // The maintenance hook should NOT apply to the 'hooks' action
      $this->request ('GET', '/v1/test/hooks', [], [
         'HTTP_X_FORCE_MAINTENANCE' => 'true'
      ])
         ->assertOk ();
   }

   public function test_after_hook_with_except_scope (): void
   {
      // The addTimestamp hook has except: ['noAfterHook']
      // So it should NOT add X-Timestamp to noAfterHook
      $response = $this->get ('/v1/test/no-after-hook')
         ->assertOk ()
         ->getResponse ();

      $this->assertNull ($response->headers->get ('X-Timestamp'));
      // But X-Hook-After should still be there (no except on that hook)
      $this->assertEquals ('executed', $response->headers->get ('X-Hook-After'));
   }

   public function test_after_hook_adds_timestamp_to_other_actions (): void
   {
      $response = $this->get ('/v1/test/hooks')
         ->assertOk ()
         ->getResponse ();

      $this->assertNotNull ($response->headers->get ('X-Timestamp'));
   }

   // === Response::accepted() Tests ===

   public function test_accepted_response_returns_202 (): void
   {
      $this->post ('/v1/test/async-job')
         ->assertStatus (202);
   }

   public function test_accepted_response_contains_job_id (): void
   {
      $this->post ('/v1/test/async-job')
         ->assertStatus (202)
         ->assertJsonHas ('body.job:id');
   }

   // === Bearer Token Tests ===

   public function test_bearer_required_returns_401_without_token (): void
   {
      $this->get ('/v1/test/bearer/required')
         ->assertUnauthorized ()
         ->assertJsonHas ('flash.errors');
   }

   public function test_bearer_required_returns_401_with_invalid_token (): void
   {
      $this->withToken ('invalid-token-xyz')
         ->get ('/v1/test/bearer/required')
         ->assertUnauthorized ();
   }

   public function test_bearer_required_accepts_valid_token (): void
   {
      $this->withToken ('valid-token')
         ->get ('/v1/test/bearer/required')
         ->assertOk ()
         ->assertJsonPath ('body.authenticated', true)
         ->assertJsonHas ('body.claims');
   }

   public function test_bearer_with_claims_validates_scope (): void
   {
      // admin-token has scope: admin
      $this->withToken ('admin-token')
         ->get ('/v1/test/bearer/claims')
         ->assertOk ()
         ->assertJsonPath ('body.scope', 'admin');
   }

   public function test_bearer_with_claims_rejects_wrong_scope (): void
   {
      // valid-token has scope: user, but endpoint requires scope: admin
      $this->withToken ('valid-token')
         ->get ('/v1/test/bearer/claims')
         ->assertUnauthorized ();
   }

   public function test_bearer_optional_works_without_token (): void
   {
      $this->get ('/v1/test/bearer/optional')
         ->assertOk ()
         ->assertJsonPath ('body.has_bearer', false)
         ->assertJsonPath ('body.sub', 'anonymous');
   }

   public function test_bearer_optional_works_with_valid_token (): void
   {
      $this->withToken ('valid-token')
         ->get ('/v1/test/bearer/optional')
         ->assertOk ()
         ->assertJsonPath ('body.has_bearer', true)
         ->assertJsonPath ('body.sub', 'user-123');
   }

   public function test_bearer_returns_401_for_expired_token (): void
   {
      $this->withToken ('expired-token')
         ->get ('/v1/test/bearer/required')
         ->assertUnauthorized ();
   }
}
