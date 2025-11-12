<?php

namespace Wisp\Example\Controller;

use Wisp\Http\Response;

class Examples
{
   public function __construct (
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
         <html lang="en">
         <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Wisp HTML Example</title>
            <style>
               body { font-family: system-ui; max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
               h1 { color: #4a5568; }
               code { background: #f7fafc; padding: 0.25rem 0.5rem; border-radius: 0.25rem; }
            </style>
         </head>
         <body>
            <h1>Wisp Framework - HTML Response</h1>
            <p>This is an HTML response generated using <code>$response->html()</code></p>
            <p><a href="/v1/heroes">View Heroes API</a></p>
         </body>
         </html>
      HTML;

      return $this->response->html ($html);
   }

   public function redirect ()
   {
      return $this->response->redirect ('/v1/heroes', 302);
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
