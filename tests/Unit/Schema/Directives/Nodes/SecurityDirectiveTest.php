<?php

namespace Tests\Unit\Schema\Directives\Nodes;

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
        $schema = $this->buildSchemaFromString('
        type Query @security(depth: 20) {
            me: String
        }');

        $rule = DocumentValidator::getRule(QueryDepth::class);
        $this->assertEquals(20, $rule->getMaxQueryDepth());
    }

    /**
     * @test
     */
    public function itCanSetMaxComplexity()
    {
        $schema = $this->buildSchemaFromString('
        type Query @security(complexity: 20) {
            me: String
        }');

        $rule = DocumentValidator::getRule(QueryComplexity::class);
        $this->assertEquals(20, $rule->getMaxQueryComplexity());
    }
}
