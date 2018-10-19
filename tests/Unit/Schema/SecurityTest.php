<?php

namespace Tests\Unit\Schema;

use Tests\TestCase;
use GraphQL\Validator\Rules\QueryDepth;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\DisableIntrospection;

class SecurityTest extends TestCase
{
    /**
     * @test
     * @deprecated will be configured only by config in v3
     */
    public function itCanSetMaxQueryComplexityThroughSecurityDirective()
    {
        $this->assertMaxQueryComplexityIs1('
        type Query @security(complexity: 1) {
            user: User @first
        }
        
        type User {
            name: String
        }
        ');
    }

    /**
     * @test
     * @deprecated will be configured only by config in v3
     */
    public function itCanSetMaxDepthThroughSecurityDirective()
    {
        $this->assertMaxQueryDepthIs1('
        type Query @security(depth: 1) {
            user: User @first
        }
        
        type User {
            name: String
            user: User
        }
        ');
    }

    /**
     * @test
     * @deprecated will be configured only by config in v3
     */
    public function itCanDisableIntrospectionThroughSecurityDirective()
    {
        $this->assertIntrospectionIsDisabled('
        type Query @security(introspection: false) {
            foo: Int
        }
        ');
    }

    /**
     * @test
     */
    public function itCanSetMaxComplexityThroughConfig()
    {
        config(['lighthouse.security.max_query_complexity' => 1]);

        $this->assertMaxQueryComplexityIs1('
        type Query {
            user: User @first
        }
        
        type User {
            name: String
        }
        ');
    }

    /**
     * @test
     */
    public function itCanSetMaxDepthThroughConfig()
    {
        config(['lighthouse.security.max_query_depth' => 1]);

        $this->assertMaxQueryDepthIs1('
        type Query {
            user: User @first
        }
        
        type User {
            name: String
            user: User
        }
        ');
    }

    /**
     * @test
     */
    public function itCanDisableIntrospectionThroughConfig()
    {
        config(['lighthouse.security.disable_introspection' => true]);

        $this->assertIntrospectionIsDisabled(
            $this->placeholderQuery()
        );
    }

    protected function assertMaxQueryComplexityIs1(string $schema)
    {
        $result = $this->execute($schema, '
        {
            user {
                name
            }
        }
        ');

        $this->assertSame(QueryComplexity::maxQueryComplexityErrorMessage(1, 2), $result['errors'][0]['message']);
    }

    protected function assertMaxQueryDepthIs1($schema)
    {
        $result = $this->execute($schema,'
        {
            user {
                user {
                    user {
                        name
                    }
                }
            }
        }
        ');

        $this->assertSame(QueryDepth::maxQueryDepthErrorMessage(1, 2), $result['errors'][0]['message']);
    }

    /**
     * @param $schema
     */
    protected function assertIntrospectionIsDisabled(string $schema)
    {
        $result = $this->execute($schema,'
        {
            __schema {
                queryType {
                    name
                }
            }
        }
        ');

        $this->assertSame(DisableIntrospection::introspectionDisabledMessage(), $result['errors'][0]['message']);
    }
}
