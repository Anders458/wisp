<?php

namespace App\Tests\Feature;

use App\Shape\CreateUserRequest;
use Wisp\Testing\FixtureFactory;
use Wisp\Testing\TestCase;

class UserTest extends TestCase
{
   private FixtureFactory $fixtures;

   protected function setUp (): void
   {
      parent::setUp ();
      $this->fixtures = new FixtureFactory ();
   }

   public function test_health_check (): void
   {
      $this->get ('/v1/health')
         ->assertOk ()
         ->assertJsonPath ('body.status', 'ok');
   }

   public function test_list_users_requires_auth (): void
   {
      $this->get ('/v1/users')
         ->assertForbidden ()
         ->assertJsonHas ('flash.errors');
   }

   public function test_list_users_as_authenticated_user (): void
   {
      $this->withToken ('user-token-123')
         ->get ('/v1/users')
         ->assertOk ()
         ->assertJsonHas ('body.users')
         ->assertJsonHas ('body.meta')
         ->assertJsonCount (2, 'body.users');
   }

   public function test_create_user_with_valid_data (): void
   {
      $data = $this->fixtures->create (CreateUserRequest::class);

      $this->post ('/v1/users', $data)
         ->assertCreated ()
         ->assertJsonPath ('body.email', $data ['email'])
         ->assertJsonPath ('body.name', $data ['name']);
   }

   public function test_create_user_with_invalid_email (): void
   {
      $data = $this->fixtures->create (CreateUserRequest::class, [
         'email' => 'not-an-email'
      ]);

      $this->post ('/v1/users', $data)
         ->assertStatus (422)
         ->assertJsonHas ('flash.violations');
   }

   public function test_create_user_with_short_password (): void
   {
      $data = $this->fixtures->create (CreateUserRequest::class, [
         'password' => 'short'
      ]);

      $this->post ('/v1/users', $data)
         ->assertStatus (422)
         ->assertJsonHas ('flash.violations');
   }

   public function test_show_user (): void
   {
      $this->withToken ('user-token-123')
         ->get ('/v1/users/1')
         ->assertOk ()
         ->assertJsonPath ('body.id', 1);
   }

   public function test_admin_endpoint_forbidden_for_regular_user (): void
   {
      $this->withToken ('user-token-123')
         ->get ('/v1/admin/users')
         ->assertForbidden ();
   }

   public function test_admin_endpoint_accessible_for_admin (): void
   {
      $this->withToken ('admin-token-456')
         ->get ('/v1/admin/users')
         ->assertOk ()
         ->assertJsonPath ('body.admin_only', true);
   }

   public function test_me_without_auth (): void
   {
      $this->get ('/v1/users/@me')
         ->assertUnauthorized ()
         ->assertJsonHas ('flash.errors');
   }

   public function test_me_with_token (): void
   {
      $this->withToken ('user-token-123')
         ->get ('/v1/users/@me')
         ->assertOk ()
         ->assertJsonHas ('body.id')
         ->assertJsonHas ('body.roles');
   }
}
