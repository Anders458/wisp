<?php

describe ('GET /v1/examples/download', function () {
   test ('returns file download or 404 if not exists', function () {
      $this->client->get ('/v1/examples/download');

      $response = $this->client->getResponse ();
      expect ($response->getStatusCode ())->toBeIn ([ 200, 404 ]);
   });
});

describe ('GET /v1/examples/html', function () {
   test ('returns html response', function () {
      $this->client->get ('/v1/examples/html')
         ->assertOk ();

      $response = $this->client->getResponse ();
      expect ($response->getHeader ('content-type'))->toContain ('text/html');

      $crawler = $this->client->getCrawler ();
      expect ($crawler->filter ('h1')->count ())->toBeGreaterThan (0);
   });
});

describe ('GET /v1/examples/redirect', function () {
   test ('redirects to specified url', function () {
      $this->client->followRedirects (false);
      $this->client->get ('/v1/examples/redirect')
         ->assertStatus (302);

      expect (true)->toBeTrue ();
   });
});

describe ('GET /v1/examples/session-test', function () {
   test ('increments counter across requests', function () {
      $first = $this->client->get ('/v1/examples/session-test')->toArray ();
      expect ($first ['body'] ['counter'])->toBe (1);

      $second = $this->client->get ('/v1/examples/session-test')->toArray ();
      expect ($second ['body'] ['counter'])->toBe (2);

      $third = $this->client->get ('/v1/examples/session-test')->toArray ();
      expect ($third ['body'] ['counter'])->toBe (3);
   });
});

describe ('GET /v1/examples/text', function () {
   test ('returns text response', function () {
      $response = $this->client->get ('/v1/examples/text')
         ->assertOk ()
         ->getResponse ();

      expect ($response->getHeader ('content-type'))->toContain ('text/plain');
   });
});

describe ('POST /v1/examples/form', function () {
   test ('accepts form data', function () {
      $data = $this->client->post ('/v1/examples/form', [
         'name' => 'Test User',
         'email' => 'test@example.com'
      ])->assertOk ()
         ->toArray ();

      expect ($data ['body'] ['data'])->toHaveKey ('name');
      expect ($data ['body'] ['data'])->toHaveKey ('email');
   });
});

describe ('POST /v1/examples/validation', function () {
   test ('rejects invalid data', function () {
      $data = $this->client->post ('/v1/examples/validation', [
         'email' => 'invalid-email',
         'age' => 15
      ])->assertStatus (422)
         ->toArray ();

      expect ($data ['body'])->toHaveKey ('errors');
   });

   test ('accepts valid data', function () {
      $user = \Tests\createValidUserFixture ();

      $this->client->post ('/v1/examples/validation', $user)
         ->assertOk ();

      expect (true)->toBeTrue ();
   });

   test ('validates email format', function () {
      $user = \Tests\createValidUserFixture ([ 'email' => 'notanemail' ]);

      $data = $this->client->post ('/v1/examples/validation', $user)
         ->assertStatus (422)
         ->toArray ();

      expect ($data ['body'] ['errors'])->toHaveKey ('email');
   });

   test ('validates age range', function () {
      $user = \Tests\createValidUserFixture ([ 'age' => 10 ]);

      $data = $this->client->post ('/v1/examples/validation', $user)
         ->assertStatus (422)
         ->toArray ();

      expect ($data ['body'] ['errors'])->toHaveKey ('age');
   });
});
