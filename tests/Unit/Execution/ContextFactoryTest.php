<?php declare(strict_types=1);

namespace Tests\Unit\Execution;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Execution\ContextFactory;
use Nuwave\Lighthouse\Execution\HttpGraphQLContext;
use Nuwave\Lighthouse\Execution\UserGraphQLContext;
use Tests\TestCase;

final class ContextFactoryTest extends TestCase
{
    public function testGenerateHttpContext(): void
    {
        $contextFactory = new ContextFactory();
        $context = $contextFactory->generate(new Request());
        $this->assertInstanceOf(HttpGraphQLContext::class, $context);
    }

    public function testGenerateUserContext(): void
    {
        $contextFactory = new ContextFactory();
        $context = $contextFactory->generate(null);
        $this->assertInstanceOf(UserGraphQLContext::class, $context);
    }
}
