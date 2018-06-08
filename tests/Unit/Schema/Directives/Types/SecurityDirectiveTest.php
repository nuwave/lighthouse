<?php

namespace Tests\Unit\Schema\Directives\Types;

use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use Tests\TestCase;

class SecurityDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanSetMaxDepth()
    {
        $this->buildSchemaFromString('
        type Query @security(depth: 3) {
            me: String
        }
        ');

        $rule = DocumentValidator::getRule(QueryDepth::class);
        $this->assertEquals(3, $rule->getMaxQueryDepth());
    }

    /**
     * @test
     */
    public function itCanSetMaxComplexity()
    {
        $this->buildSchemaFromString('
        type Query @security(complexity: 3) {
            me: String
        }
        ');

        $rule = DocumentValidator::getRule(QueryComplexity::class);
        $this->assertEquals(3, $rule->getMaxQueryComplexity());
    }
}
