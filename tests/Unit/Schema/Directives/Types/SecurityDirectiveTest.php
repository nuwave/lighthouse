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
        /** @var QueryDepth $ruleBefore */
        $ruleBefore = DocumentValidator::getRule(QueryDepth::class);
        $depthBefore = $ruleBefore->getMaxQueryDepth();

        $this->buildSchemaFromString('
            type Query @security(depth: 3) {
                me: String
            }
        ');

        /** @var QueryDepth $rule */
        $ruleAfter = DocumentValidator::getRule(QueryDepth::class);
        DocumentValidator::defaultRules();
        $depthAfter = $ruleAfter->getMaxQueryDepth();

        $this->assertSame(3, $depthAfter);
        $this->assertNotSame($depthBefore, $depthAfter);

        // Reset this rule to allow tests following this to function normally
        DocumentValidator::addRule($ruleBefore);
    }

    /**
     * @test
     */
    public function itCanSetMaxComplexity()
    {
        /** @var QueryComplexity $ruleBefore */
        $ruleBefore = DocumentValidator::getRule(QueryComplexity::class);
        $complexityBefore = $ruleBefore->getMaxQueryComplexity();

        $this->buildSchemaFromString('
            type Query @security(complexity: 3) {
                me: String
            }
        ');

        /** @var QueryComplexity $ruleAfter */
        $ruleAfter = DocumentValidator::getRule(QueryComplexity::class);
        $complexityAfter = $ruleAfter->getMaxQueryComplexity();

        $this->assertSame(3, $complexityAfter);
        $this->assertNotSame($complexityBefore, $complexityAfter);

        // Reset this rule to allow tests following this to function normally
        DocumentValidator::addRule($ruleBefore);
    }

    /**
     * @test
     */
    public function itCanDisableIntrospection()
    {
        $introspectionQuery = '
            {
                __schema {
                    queryType {
                        name
                    }
                }
            } 
        ';

        $allowed = $this->execute('
            type Query{
                me: String
            }
        ', $introspectionQuery);
        $this->assertAttributeEmpty('errors', $allowed);
        $this->assertAttributeNotEmpty('data', $allowed);

        $disallowed = $this->execute('
            type Query @security(introspection: false){
                me: String
            }
        ', $introspectionQuery);
        $this->assertAttributeNotEmpty('errors', $disallowed);
        $this->assertAttributeEmpty('data', $disallowed);
    }
}
