<?php

describe ('POST /v1/gateway/tokens/login', function () {
   test ('can login with token gateway', function () {
      $user = \Tests\createUser ();

      $data = $this->client->post ('/v1/gateway/tokens/login', $user)
         ->assertOk ()
         ->toArray ();

      expect ($data ['body'])->toHaveKey ('access_token');
      expect ($data ['body'])->toHaveKey ('refresh_token');
   });

   test ('rejects invalid credentials', function () {
      $this->client->post ('/v1/gateway/tokens/login', [
         'email' => 'invalid@example.com',
         'password' => 'wrongpass'
      ])->assertStatus (401);

      expect (true)->toBeTrue ();
   });
});

describe ('POST /v1/gateway/tokens/logout', function () {
   test ('logout requires authorization token', function () {
      $this->client->post ('/v1/gateway/tokens/logout')
         ->assertStatus (401);

      expect (true)->toBeTrue ();
   });
});

describe ('POST /v1/gateway/tokens/refresh', function () {
   test ('can refresh token with valid refresh token', function () {
      $user = \Tests\createUser ();

      $loginData = $this->client->post ('/v1/gateway/tokens/login', $user)
         ->toArray ();

      $refreshToken = $loginData ['body'] ['refresh_token'] ?? null;

      $data = $this->client->post ('/v1/gateway/tokens/refresh', [
         'refresh_token' => $refreshToken
      ])->assertOk ()
         ->toArray ();

      expect ($data ['body'])->toHaveKey ('access_token');
      expect ($data ['body'])->toHaveKey ('refresh_token');
   });

   test ('rejects invalid refresh token', function () {
      $this->client->post ('/v1/gateway/tokens/refresh', [
         'refresh_token' => 'invalid-token'
      ])->assertStatus (401);

      expect (true)->toBeTrue ();
   });
});
