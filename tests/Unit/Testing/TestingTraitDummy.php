<?php

namespace Tests\Unit\Testing;

use Laravel\Lumen\Testing\Concerns\MakesHttpRequests;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequestsLumen;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Orchestra\Testbench\TestCase;

/**
 * Just a placeholder in order for PHPStan to be able to analyse those traits, see https://phpstan.org/blog/how-phpstan-analyses-traits.
 */
final class TestingTraitDummy extends TestCase
{
    use MakesHttpRequests;
    use MakesGraphQLRequestsLumen;
    use RefreshesSchemaCache;
}
