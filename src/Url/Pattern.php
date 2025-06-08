<?php

namespace Wisp\Url;

class Pattern
{
   private string $raw;
   private string $compiled;
   private ?array $matches;

   public function __construct (string $raw)
   {
      $this->raw = $raw;
      $this->compile ();
      $this->matches = null;
   }

   /*
      Raw: /blog(/{slug:[A-Za-z0-9\-]+}){1,3}(/{tail}){2}
      
      Expand repeated capture groups ((...){m,n}, (...){m}, [...]{m,n}, [...]{m}):
         /blog(?:/{slug:[A-Za-z0-9\-]+})(?:/{slug:[A-Za-z0-9\-]+})?(?:/{slug:[A-Za-z0-9\-]+})?(?:/{tail})(?:/{tail})

      Rewrite duplicate parameter group names:
         /blog(?:/{slug1:[A-Za-z0-9\-]+})(?:/{slug2:[A-Za-z0-9\-]+})?(?:/{slug3:[A-Za-z0-9\-]+})?(?:/{tail1})(?:/{tail2})

      Convert named parameter groups:
         /blog(?:/(?P<slug1>[A-Za-z0-9\-]+))(?:/(?P<slug2>[A-Za-z0-9\-]+))?(?:/(?P<slug3>[A-Za-z0-9\-]+))?(?:/(?P<tail1>))(?:/(?P<tail2>))

      Raw: /blog[/{year:[0-9]{4}}[/{month:[0-9]{2}}[/{day:[0-9]{2}}]]]

      Convert optional sections:
         /blog(?:/{year:[0-9]{4}}(?:/{month:[0-9]{2}}(?:/{day:[0-9]{2}})?)?)?
      
      Convert named paramter groups:
         /blog(?:/(?P<year>[0-9]{4})(?:/(?P<month>[0-9]{2})(?:/(?P<day>[0-9]{2}))?)?)?
   
   */
   private function compile () : void
   {
      $pattern = $this->raw;

      // Expand repeated capture groups ((...){m,n}, (...){m}, [...]{m,n}, [...]{m})
      
      $stack = 0;

      $cursor = 0;
      $length = strlen ($pattern);

      $start = 0;
      $end = 0;

      while ($cursor < $length) {
         $character = $pattern [$cursor];

         if ($character === '(') {
            if ($stack === 0) {
               $start = $cursor;
            }

            $stack++;
         }

         if ($character === ')') {
            $stack--;
            
            // Found an outside group /blog(/...){1,3}
            if ($stack === 0) {
               $end = $cursor;

               if (preg_match ('/\G{(\d+),?(\d+)?}/', $pattern, $match, offset: $cursor + 1)) {
                  $min = (int) $match [1];
                  $max = (int) (!empty ($match [2]) ? $match [2] : $match [1]);

                  $repeat = substr ($pattern, $start, $end - $start + 1);
                  $repeat = trim ($repeat, '()');

                  $replace = '';

                  for ($i = 1; $i <= $max; $i++) {
                     $replace .= '(?:' . $repeat . ')';

                     if ($i > $min) {
                        $replace .= '?';
                     }
                  }

                  $pattern = substr_replace (
                     $pattern, 
                     $replace, 
                     $start, 
                     $end + 1 + strlen ($match [0]) - $start
                  );

                  $cursor = $start + strlen ($replace);
                  $length = strlen ($pattern);

                  continue;
               }
            }
         }

         $cursor++;
      }

      // Convert optional groups from [... [... [... ]]] to (?:...(?:...(?:...)?)?)?

      $stack = 0;
      $inner = 0;

      $cursor = 0;
      $length = strlen ($pattern);

      $start = 0;
      $end = 0;

      while ($cursor < $length) {
         $character = $pattern [$cursor];

         if ($character === '[' && $inner === 0) {
            $stack++;
            $start = $cursor;
         }

         if ($character === '{') {
            $inner++;
         }

         if ($character === '}') {
            $inner--;
         }

         if ($character === ']' && $inner === 0) {
            $stack--;
            $end = $cursor;

            $pattern = substr_replace (
               $pattern,
               '(?:' . substr ($pattern, $start + 1, $end - $start - 1) . ')?',
               $start,
               $end - $start + 1
            );

            $cursor = 0;
            $length = strlen ($pattern);

            continue;
         }

         $cursor++;
      }

      // Convert named match groups to indexed (?P<xy>)

      $stack = 0;

      $cursor = 0;
      $length = strlen ($pattern);

      $start = 0;
      $end = 0;

      $index = 1;

      while ($cursor < $length) {
         $character = $pattern [$cursor];

         if ($character === '{') {
            if ($stack === 0) {
               $start = $cursor;
            }

            $stack++;
         }

         if ($character === '}') {
            $stack--;

            if ($stack === 0) {
               $end = $cursor;

               $param = substr ($pattern, $start, $end - $start + 1);

               if (preg_match ('/{([^:]+):?(.*)}/', $param, $match)) {
                  if (!empty ($match [2])) {
                     $regex = $match [2];
                  } else {
                     $regex = '[A-Za-z0-9\-\_%]+';
                  }

                  $replace = '(?P<' . $match [1] . '_' . $index++ . '>' . $regex . ')';

                  $pattern = substr_replace (
                     $pattern,
                     $replace,
                     $start,
                     $end - $start + 1
                  );

                  $cursor = $start + strlen ($replace);
                  $length = strlen ($pattern);

                  continue;
               }
            }
         }

         $cursor++;
      }

      $this->compiled = $pattern;
   }

   public function getMatchedGroups () : array | false 
   {
      if ($this->matches === null) {
         return false;
      }

      return $this->matches;
   }
   
   public function isPrefixOf (string $path) : bool
   {
      return preg_match ('~^' . $this->compiled . '~', $path);
   }

   public function matches (string $path) : bool
   {
      $this->matches = null;

      if (preg_match ('~^' . $this->compiled . '$~', $path, $match, PREG_UNMATCHED_AS_NULL)) {
         $this->matches = [];

         $match = array_slice ($match, 1);

         foreach ($match as $key => $value) {
            if (is_numeric ($key)) {
               continue;
            }

            if (preg_match ('/(.*)_(\d+)/', $key, $parts)) {
               $value = urldecode ($value);
               
               if (!isset ($this->matches [$parts [1]])) {
                  $this->matches [$parts [1]] = $value;
               } else {
                  if (!is_array ($this->matches [$parts [1]])) {
                     $this->matches [$parts [1]] = [ $this->matches [$parts [1]] ];
                  }

                  $this->matches [$parts [1]] [] = $value;
               }
            }
         }
      }

      return $this->matches !== null;
   }
}