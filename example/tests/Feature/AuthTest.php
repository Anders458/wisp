<?php

namespace App\Tests\Feature;

use Wisp\Testing\TestCase;

class AuthTest extends TestCase
{
   public function test_token_login_with_valid_credentials (): void
   {
      $this->post ('/v1/auth/token', [
            'email' => 'user@example.com',
            'password' => 'password123'
         ])
         ->assertOk ()
         ->assertJsonHas ('body.token')
         ->assertJsonPath ('body.type', 'bearer')
         ->assertJsonPath ('body.user.email', 'user@example.com');
   }

   public function test_token_login_with_invalid_credentials (): void
   {
      $this->post ('/v1/auth/token', [
            'email' => 'user@example.com',
            'password' => 'wrongpassword'
         ])
         ->assertUnauthorized ()
         ->assertJsonHas ('flash.errors');
   }

   public function test_token_login_with_invalid_email (): void
   {
      $this->post ('/v1/auth/token', [
            'email' => 'not-an-email',
            'password' => 'password123'
         ])
         ->assertStatus (422)
         ->assertJsonHas ('flash.violations');
   }

   public function test_token_login_returns_admin_roles (): void
   {
      $this->post ('/v1/auth/token', [
            'email' => 'admin@example.com',
            'password' => 'admin123'
         ])
         ->assertOk ()
         ->assertJsonPath ('body.user.email', 'admin@example.com')
         ->assertJsonHas ('body.user.roles');
   }

   public function test_logout (): void
   {
      $this->post ('/v1/auth/logout')
         ->assertOk ()
         ->assertJsonHas ('flash.success');
   }

}
