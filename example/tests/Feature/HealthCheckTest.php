<?php

describe ('GET /v1/health-check', function () {
   test ('returns healthy status', function () {
      $data = $this->client->get ('/v1/health-check')
         ->assertOk ()
         ->toArray ();

      expect ($data ['body'] ['status'])->toBe ('ok');
   });
});
