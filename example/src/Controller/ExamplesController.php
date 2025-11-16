<?php

namespace Wisp\Example\Controller;

use Example\Request\CreateUserRequest;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Http\ValidationException;

class ExamplesController
{
   public function __construct (
      private Request $request,
      private Response $response
   )
   {
   }

   public function download ()
   {
      $logFile = __DIR__ . '/../../logs/wisp.log';

      if (file_exists ($logFile)) {
         return $this->response->download ($logFile, 'wisp.log');
      }

      return $this->response
         ->status (404)
         ->error (__ ('examples.log_not_found'));
   }

   public function validation ()
   {
      try {
         // Validate request using DTO
         $dto = $this->request->validate (CreateUserRequest::class);

         // If validation passes, return the validated data
         return $this->response->json ([
            'message' => 'User created successfully',
            'user' => [
               'email' => $dto->email,
               'username' => $dto->username,
               'age' => $dto->age,
               'role' => $dto->role
            ]
         ]);
      } catch (ValidationException $e) {
         // Return validation errors
         $errors = [];
         foreach ($e->getViolations () as $violation) {
            $field = $violation->getPropertyPath ();
            $errors [$field] [] = $violation->getMessage ();
         }

         return $this->response
            ->status (422)
            ->json ([
               'message' => 'Validation failed',
               'errors' => $errors
            ]);
      }
   }

   public function html ()
   {
      $title = __ ('examples.html.title');
      $loginHeading = __ ('examples.html.login_heading');
      $logoutHeading = __ ('examples.html.logout_heading');
      $emailLabel = __ ('examples.html.email_label');
      $passwordLabel = __ ('examples.html.password_label');
      $loginButton = __ ('examples.html.login_button');
      $logoutButton = __ ('examples.html.logout_button');
      $testCredentials = __ ('examples.html.test_credentials');

      $html = <<<HTML
         <!DOCTYPE html>
         <html>
         <head>
            <title>{$title}</title>
         </head>
         <body>
            <h1>{$loginHeading}</h1>
            <form method="POST" action="/v1/gateway/cookie/login">
               <label>{$emailLabel} <input type="email" name="email" required /></label><br />
               <label>{$passwordLabel} <input type="password" name="password" required /></label><br />
               <button type="submit">{$loginButton}</button>
            </form>

            <p>{$testCredentials}</p>

            <hr />

            <h1>{$logoutHeading}</h1>
            <form method="POST" action="/v1/gateway/cookie/logout">
               <button type="submit">{$logoutButton}</button>
            </form>
         </body>
         </html>
      HTML;

      return $this->response->html ($html);
   }

   public function redirect ()
   {
      return $this->response->redirect ('/v1/health-check', 302);
   }

   public function form ()
   {
      $name = $this->request->input ('name');
      $email = $this->request->input ('email');

      return $this->response->json ([
         'message' => __ ('examples.form_submitted'),
         'data' => [
            'name' => $name,
            'email' => $email
         ]
      ]);
   }

   public function text ()
   {
      $text = __ ('examples.text.title') . "\n\n";
      $text .= __ ('examples.text.description') . "\n\n";
      $text .= __ ('examples.text.features_title') . "\n";
      $text .= "- " . __ ('examples.text.feature_clean_api') . "\n";
      $text .= "- " . __ ('examples.text.feature_fast_routing') . "\n";
      $text .= "- " . __ ('examples.text.feature_powerful_middleware') . "\n";

      return $this->response->text ($text);
   }
}
