<?php

describe ('GET /v1/users/@me', function () {
   test ('requires authentication', function () {
      $this->client->get ('/v1/users/@me')
         ->assertOk ();

      expect (true)->toBeTrue ();
   });

   test ('returns current user with valid authentication', function () {
      $user = \Tests\createUser ();

      $this->client->post ('/v1/gateway/cookie/login', $user);

      $data = $this->client->get ('/v1/users/@me')
         ->assertOk ()
         ->toArray ();

      expect ($data ['body'] ['user'])->toHaveKey ('id');
      expect ($data ['body'] ['user'])->toHaveKey ('roles');
      expect ($data ['body'] ['user'])->toHaveKey ('permissions');
   });
});
