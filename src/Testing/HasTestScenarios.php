<?php

namespace Wisp\Testing;

/**
 * Interface for routes that require multi-step test scenarios.
 *
 * Use this for endpoints that need setup steps (like authentication)
 * before the main request can be tested.
 */
interface HasTestScenarios
{
   /**
    * Return an array of test scenarios.
    *
    * Each scenario has:
    * - 'setup': Optional array of requests to run before the main request
    * - 'request': The main request configuration (method, headers, body)
    * - 'expect': Expected outcome ('success', 'auth_error', 'validation_error', 'error')
    *
    * Setup requests can extract values from responses using 'extract' which
    * maps response paths to variables that can be used in subsequent requests.
    *
    * @return array<string, array{
    *    setup?: array<int, array{
    *       method: string,
    *       path: string,
    *       body?: array<string, mixed>,
    *       headers?: array<string, string>,
    *       extract?: array<string, string>
    *    }>,
    *    request?: array{
    *       headers?: array<string, string>,
    *       body?: array<string, mixed>
    *    },
    *    expect: 'success'|'auth_error'|'validation_error'|'error'
    * }>
    */
   public static function testScenarios (): array;
}
