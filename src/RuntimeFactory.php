<?php

namespace Wisp;

class RuntimeFactory
{
   public function __construct (
      private string $version,
      private string $defaultStage,
      private bool $defaultDebug,
      private bool $detectStageFromCli,
      private bool $detectDebugFromCli,
      private array $hostnameMap,
      private bool $debugQueryEnabled,
      private ?string $debugQuerySecret,
      private array $debugAllowedStages
   )
   {
   }

   public function create (): Runtime
   {
      $builder = Runtime::configure ()
         ->version ($this->version)
         ->stage ($this->resolveDefaultStage ())
         ->debug ($this->defaultDebug);

      if ($this->detectStageFromCli) {
         $builder->detectStageFromCli ();
      }

      if ($this->detectDebugFromCli) {
         $builder->detectDebugFromCli ();
      }

      if (!empty ($this->hostnameMap)) {
         $builder->detectStageFromHostname ($this->buildHostnameMap ());
      }

      if ($this->debugQueryEnabled) {
         $builder->allowDebugFromQuery ($this->debugQuerySecret);
      }

      if (!empty ($this->debugAllowedStages)) {
         $builder->allowDebugInStages ($this->buildAllowedStages ());
      }

      return $builder->build ();
   }

   private function resolveDefaultStage (): Stage
   {
      return match ($this->defaultStage) {
         'dev' => Stage::Development,
         'test' => Stage::Staging,
         'prod' => Stage::Production,
         default => Stage::Production
      };
   }

   /**
    * @return array<string, Stage>
    */
   private function buildHostnameMap (): array
   {
      $map = [];

      foreach ($this->hostnameMap as $hostname => $stage) {
         $map [$hostname] = match ($stage) {
            'dev' => Stage::Development,
            'test' => Stage::Staging,
            'prod' => Stage::Production,
            default => Stage::Production
         };
      }

      return $map;
   }

   /**
    * @return Stage[]
    */
   private function buildAllowedStages (): array
   {
      return array_map (
         fn (string $s) => match ($s) {
            'dev' => Stage::Development,
            'test' => Stage::Staging,
            'prod' => Stage::Production,
            default => Stage::Production
         },
         $this->debugAllowedStages
      );
   }
}
