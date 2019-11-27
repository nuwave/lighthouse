<?php

namespace Tests\Unit\Schema;

use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    public function testCanSetMaxComplexityThroughConfig(): void
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

    public function testCanSetMaxDepthThroughConfig(): void
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

    public function testCanDisableIntrospectionThroughConfig(): void
    {
        config(['lighthouse.security.disable_introspection' => true]);

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
