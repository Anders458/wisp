<?php

namespace Wisp\Example\Controller;

use Wisp\Http\Request;
use Wisp\Http\Response;

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
         ->error ('Log file not found');
   }

   public function html ()
   {
      $html = <<<'HTML'
         <!DOCTYPE html>
         <html>
         <head>
            <title>Cookie Authentication</title>
         </head>
         <body>
            <h1>Login</h1>
            <form method="POST" action="/v1/gateway/cookie/login">
               <label>Email: <input type="email" name="email" required /></label><br />
               <label>Password: <input type="password" name="password" required /></label><br />
               <button type="submit">Login</button>
            </form>

            <p>Test: user@example.com / secret or admin@example.com / secret</p>

            <hr />

            <h1>Logout</h1>
            <form method="POST" action="/v1/gateway/cookie/logout">
               <button type="submit">Logout</button>
            </form>
         </body>
         </html>
      HTML;

      return $this->response->html ($html);
   }

   public function redirect ()
   {
      return $this->response->redirect ('/v1/heroes', 302);
   }

   public function form ()
   {
      $name = $this->request->input ('name');
      $email = $this->request->input ('email');

      return $this->response->json ([
         'message' => 'Form submitted successfully (CSRF validated)',
         'data' => [
            'name' => $name,
            'email' => $email
         ]
      ]);
   }

   public function text ()
   {
      $text = "Wisp Framework - Plain Text Response\n\n";
      $text .= "This is a plain text response generated using \$response->text()\n\n";
      $text .= "Features:\n";
      $text .= "- Clean API\n";
      $text .= "- Fast routing\n";
      $text .= "- Powerful middleware\n";

      return $this->response->text ($text);
   }
}
