<?php

namespace Tests\Unit\Execution;

use Tests\TestCase;
use Tests\Utils\Models\Post;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Execution\MutationExecutor;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

class MutationExecutorTest extends TestCase
{
    /**
     * @test
     */
    public function itThrowsIfRelationMethodReturnTypeIsMissing(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessageRegExp('/nonTypeHinted/');

        MutationExecutor::executeCreate(
            new Post(),
            new Collection([
                'nonTypeHinted' => 'Lighthouse will try to determine the return type of this method and fail.',
            ])
        );
    }
}
