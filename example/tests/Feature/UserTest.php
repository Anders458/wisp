<?php

namespace App\Tests\Feature;

use App\Dto\CreateUserRequest;
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
      $this->get ('/api/health')
         ->assertOk ()
         ->assertJsonPath ('data.status', 'ok');
   }

   public function test_list_users_requires_auth (): void
   {
      $this->get ('/api/users')
         ->assertUnauthorized ();
   }

   public function test_list_users_as_authenticated_user (): void
   {
      $this->withToken ('user-token-123')
         ->get ('/api/users')
         ->assertOk ()
         ->assertJsonHas ('data.users')
         ->assertJsonHas ('data.meta')
         ->assertJsonCount (2, 'data.users');
   }

   public function test_create_user_with_valid_data (): void
   {
      $data = $this->fixtures->create (CreateUserRequest::class);

      $this->post ('/api/users', $data)
         ->assertCreated ()
         ->assertJsonPath ('data.email', $data ['email'])
         ->assertJsonPath ('data.name', $data ['name']);
   }

   public function test_create_user_with_invalid_email (): void
   {
      $data = $this->fixtures->create (CreateUserRequest::class, [
         'email' => 'not-an-email'
      ]);

      $this->post ('/api/users', $data)
         ->assertStatus (422);
   }

   public function test_create_user_with_short_password (): void
   {
      $data = $this->fixtures->create (CreateUserRequest::class, [
         'password' => 'short'
      ]);

      $this->post ('/api/users', $data)
         ->assertStatus (422);
   }

   public function test_show_user (): void
   {
      $this->withToken ('user-token-123')
         ->get ('/api/users/1')
         ->assertOk ()
         ->assertJsonPath ('data.id', 1);
   }

   public function test_admin_endpoint_forbidden_for_regular_user (): void
   {
      $this->withToken ('user-token-123')
         ->get ('/api/admin/users')
         ->assertForbidden ();
   }

   public function test_admin_endpoint_accessible_for_admin (): void
   {
      $this->withToken ('admin-token-456')
         ->get ('/api/admin/users')
         ->assertOk ()
         ->assertJsonPath ('data.admin_only', true);
   }
}
