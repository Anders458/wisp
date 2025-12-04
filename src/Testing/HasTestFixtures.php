<?php

namespace Wisp\Testing;

/**
 * Interface for DTOs that provide their own test fixtures.
 *
 * When a DTO implements this interface, the test command will use these
 * fixtures instead of generating random data from validation constraints.
 */
interface HasTestFixtures
{
   /**
    * Return an array of named test fixtures.
    *
    * The 'valid' key should contain data that will succeed validation
    * and any application-level checks (e.g., correct credentials).
    *
    * Other keys define failure cases with their expected behavior.
    *
    * @return array<string, array{data: array<string, mixed>, expect: 'success'|'validation_error'|'auth_error'|'error'}>
    */
   public static function testFixtures (): array;
}
