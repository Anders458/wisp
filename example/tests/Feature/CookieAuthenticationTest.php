<?php

describe ('POST /v1/gateway/cookie/login', function () {
   test ('can login with valid credentials', function () {
      $user = \Tests\createUser ();

      $data = $this->client->post ('/v1/gateway/cookie/login', $user)
         ->assertOk ()
         ->toArray ();

      expect ($data ['body'])->toHaveKey ('user');
   });

   test ('rejects login with invalid credentials', function () {
      $this->client->post ('/v1/gateway/cookie/login', [
         'email' => 'wrong@example.com',
         'password' => 'wrongpassword'
      ])->assertStatus (401);

      expect (true)->toBeTrue ();
   });

   test ('validates required fields', function () {
      $this->client->post ('/v1/gateway/cookie/login', [])
         ->assertStatus (422);

      expect (true)->toBeTrue ();
   });
});

describe ('POST /v1/gateway/cookie/logout', function () {
   test ('can logout with cookie gateway', function () {
      $user = \Tests\createUser ();

      $this->client->post ('/v1/gateway/cookie/login', $user);
      $this->client->post ('/v1/gateway/cookie/logout')
         ->assertOk ();

      expect (true)->toBeTrue ();
   });
});
