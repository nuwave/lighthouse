<?php declare(strict_types=1);

namespace Tests\Unit\Testing;

use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\MocksResolvers;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Nuwave\Lighthouse\Testing\UsesTestSchema;
use Orchestra\Testbench\TestCase;

/**
 * Just a placeholder in order for PHPStan to be able to analyze those traits, see https://phpstan.org/blog/how-phpstan-analyses-traits.
 */
final class TestingTraitDummy extends TestCase
{
    use MakesGraphQLRequests;
    use MakesHttpRequests;
    use MocksResolvers;
    use RefreshesSchemaCache;
    use UsesTestSchema;
}
