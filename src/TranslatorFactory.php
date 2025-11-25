<?php

namespace Wisp;

use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

class TranslatorFactory
{
   public static function create (string $localesDir, string $defaultLocale = 'en') : TranslatorInterface
   {
      $translator = new Translator ($defaultLocale);
      $translator->addLoader ('yaml', new YamlFileLoader ());

      if (is_dir ($localesDir)) {
         $locales = glob ($localesDir . '/*', GLOB_ONLYDIR);

         foreach ($locales as $localeDir) {
            $locale = basename ($localeDir);
            $messagesFile = $localeDir . '/messages.yaml';

            if (file_exists ($messagesFile)) {
               $translator->addResource ('yaml', $messagesFile, $locale);
            }
         }
      }

      return $translator;
   }
}
