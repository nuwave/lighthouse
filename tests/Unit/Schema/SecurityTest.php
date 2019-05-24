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
     */
    public function itCanSetMaxComplexityThroughConfig(): void
    {
        config(['lighthouse.security.max_query_complexity' => 1]);

        $this->schema = '
        type Query {
            user: User @first
        }
        
        type User {
            name: String
        }
        ';

        $this->assertMaxQueryComplexityIs1();
    }

    /**
     * @test
     */
    public function itCanSetMaxDepthThroughConfig(): void
    {
        config(['lighthouse.security.max_query_depth' => 1]);

        $this->schema = '
        type Query {
            user: User @first
        }
        
        type User {
            name: String
            user: User
        }
        ';

        $this->assertMaxQueryDepthIs1();
    }

    /**
     * @test
     */
    public function itCanDisableIntrospectionThroughConfig(): void
    {
        config(['lighthouse.security.disable_introspection' => true]);

        $this->schema = $this->placeholderQuery();

        $this->assertIntrospectionIsDisabled();
    }

    protected function assertMaxQueryComplexityIs1(): void
    {
        $result = $this->graphQL('
        {
            user {
                name
            }
        }
        ');

        $this->assertSame(
            QueryComplexity::maxQueryComplexityErrorMessage(1, 2),
            $result->jsonGet('errors.0.message')
        );
    }

    protected function assertMaxQueryDepthIs1(): void
    {
        $result = $this->graphQL('
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

        $this->assertSame(
            QueryDepth::maxQueryDepthErrorMessage(1, 2),
            $result->jsonGet('errors.0.message')
        );
    }

    protected function assertIntrospectionIsDisabled(): void
    {
        $result = $this->graphQL('
        {
            __schema {
                queryType {
                    name
                }
            }
        }
        ');

        $this->assertSame(
            DisableIntrospection::introspectionDisabledMessage(),
            $result->jsonGet('errors.0.message')
        );
    }
}
