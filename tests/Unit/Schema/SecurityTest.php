<?php declare(strict_types=1);

namespace Tests\Unit\Schema;

use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use Tests\TestCase;

final class SecurityTest extends TestCase
{
    public function testSetMaxComplexityThroughConfig(): void
    {
        config(['lighthouse.security.max_query_complexity' => 1]);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user: User @first
        }

        type User {
            name: String
        }
        GRAPHQL;

        $this->assertMaxQueryComplexityIs1();
    }

    public function testSetMaxDepthThroughConfig(): void
    {
        config(['lighthouse.security.max_query_depth' => 1]);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user: User @first
        }

        type User {
            name: String
            user: User
        }
        GRAPHQL;

        $this->assertMaxQueryDepthIs1();
    }

    public function testDisableIntrospectionThroughConfig(): void
    {
        config(['lighthouse.security.disable_introspection' => DisableIntrospection::ENABLED]);

        $this->assertIntrospectionIsDisabled();
    }

    private function assertMaxQueryComplexityIs1(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user {
                name
            }
        }
        GRAPHQL)->assertGraphQLErrorMessage(
            QueryComplexity::maxQueryComplexityErrorMessage(1, 2),
        );
    }

    private function assertMaxQueryDepthIs1(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user {
                user {
                    user {
                        name
                    }
                }
            }
        }
        GRAPHQL)->assertGraphQLErrorMessage(
            QueryDepth::maxQueryDepthErrorMessage(1, 2),
        );
    }

    private function assertIntrospectionIsDisabled(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            __schema {
                queryType {
                    name
                }
            }
        }
        GRAPHQL)->assertGraphQLErrorMessage(
            DisableIntrospection::introspectionDisabledMessage(),
        );
    }
}
