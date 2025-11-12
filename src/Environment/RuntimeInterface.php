<?php

namespace Wisp\Environment;

interface RuntimeInterface
{
   public function getElapsedTime () : float;

   public function getStage () : Stage;

   public function getVersion () : string;

   public function is (Stage $stage) : bool;

   public function isCli () : bool;

   public function isDebug () : bool;
}
