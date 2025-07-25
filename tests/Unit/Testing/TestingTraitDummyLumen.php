<?php declare(strict_types=1);

namespace Tests\Unit\Testing;

use Laravel\Lumen\Testing\Concerns\MakesHttpRequests;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequestsLumen;
use Nuwave\Lighthouse\Testing\MocksResolvers;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Nuwave\Lighthouse\Testing\UsesTestSchema;
use Orchestra\Testbench\TestCase;

/**
 * Just a placeholder in order for PHPStan to be able to analyze those traits, see https://phpstan.org/blog/how-phpstan-analyses-traits.
 *
 * @deprecated lumen support will be removed in the next major version
 */
final class TestingTraitDummyLumen extends TestCase
{
    use MakesGraphQLRequestsLumen;
    use MakesHttpRequests;
    use MocksResolvers;
    use RefreshesSchemaCache;
    use UsesTestSchema;
}
