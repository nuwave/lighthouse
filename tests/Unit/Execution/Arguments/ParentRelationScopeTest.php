<?php declare(strict_types=1);

namespace Tests\Unit\Execution\Arguments;

use Nuwave\Lighthouse\Execution\Arguments\ParentRelationScope;
use Tests\TestCase;

final class ParentRelationScopeTest extends TestCase
{
    public function testUnrelatedModelEncodesNonScalarValues(): void
    {
        $this->assertSame(
            'Cannot modify a model that is not related to the given parent: a, {"b":1}.',
            ParentRelationScope::unrelatedModel(['a', ['b' => 1]]),
        );
    }
}
