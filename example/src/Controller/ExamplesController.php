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
         <html lang="en">
         <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Wisp - Cookie Authentication Login</title>
            <style>
               * { margin: 0; padding: 0; box-sizing: border-box; }
               body {
                  font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                  background: linear-gradient (135deg, #667eea 0%, #764ba2 100%);
                  min-height: 100vh;
                  display: flex;
                  align-items: center;
                  justify-content: center;
                  padding: 1rem;
               }
               .login-container {
                  background: white;
                  border-radius: 1rem;
                  box-shadow: 0 20px 60px rgba (0, 0, 0, 0.3);
                  padding: 3rem;
                  width: 100%;
                  max-width: 420px;
               }
               h1 {
                  color: #1a202c;
                  margin-bottom: 0.5rem;
                  font-size: 1.875rem;
                  font-weight: 700;
               }
               .subtitle {
                  color: #718096;
                  margin-bottom: 2rem;
                  font-size: 0.875rem;
               }
               .form-group {
                  margin-bottom: 1.5rem;
               }
               label {
                  display: block;
                  color: #4a5568;
                  font-weight: 600;
                  margin-bottom: 0.5rem;
                  font-size: 0.875rem;
               }
               input {
                  width: 100%;
                  padding: 0.75rem;
                  border: 2px solid #e2e8f0;
                  border-radius: 0.5rem;
                  font-size: 1rem;
                  transition: border-color 0.2s;
               }
               input:focus {
                  outline: none;
                  border-color: #667eea;
               }
               button {
                  width: 100%;
                  padding: 0.875rem;
                  background: linear-gradient (135deg, #667eea 0%, #764ba2 100%);
                  color: white;
                  border: none;
                  border-radius: 0.5rem;
                  font-size: 1rem;
                  font-weight: 600;
                  cursor: pointer;
                  transition: transform 0.2s, box-shadow 0.2s;
               }
               button:hover {
                  transform: translateY (-2px);
                  box-shadow: 0 10px 20px rgba (102, 126, 234, 0.4);
               }
               button:active {
                  transform: translateY (0);
               }
               .test-credentials {
                  margin-top: 2rem;
                  padding: 1rem;
                  background: #f7fafc;
                  border-radius: 0.5rem;
                  border-left: 4px solid #667eea;
               }
               .test-credentials h3 {
                  color: #2d3748;
                  font-size: 0.875rem;
                  font-weight: 600;
                  margin-bottom: 0.5rem;
               }
               .test-credentials code {
                  display: block;
                  color: #4a5568;
                  font-size: 0.875rem;
                  line-height: 1.6;
               }
               .message {
                  margin-bottom: 1.5rem;
                  padding: 0.875rem;
                  border-radius: 0.5rem;
                  font-size: 0.875rem;
               }
               .error { background: #fed7d7; color: #c53030; border: 1px solid #fc8181; }
               .success { background: #c6f6d5; color: #2f855a; border: 1px solid #9ae6b4; }
            </style>
         </head>
         <body>
            <div class="login-container">
               <h1>Cookie Authentication</h1>
               <p class="subtitle">Session-based authentication example</p>

               <form method="POST" action="/gateway/cookie/login">
                  <div class="form-group">
                     <label for="email">Email Address</label>
                     <input type="email" id="email" name="email" required placeholder="user@example.com" />
                  </div>

                  <div class="form-group">
                     <label for="password">Password</label>
                     <input type="password" id="password" name="password" required placeholder="Enter your password" />
                  </div>

                  <button type="submit">Sign In</button>
               </form>

               <div class="test-credentials">
                  <h3>Test Credentials</h3>
                  <code>
                     Email: user@example.com<br />
                     Password: secret<br />
                     <br />
                     Email: admin@example.com<br />
                     Password: secret
                  </code>
               </div>
            </div>
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
