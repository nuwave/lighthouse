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

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user: User @first
        }

        type User {
            name: String
        }
        ';

        $this->assertMaxQueryComplexityIs1();
    }

    public function testSetMaxDepthThroughConfig(): void
    {
        config(['lighthouse.security.max_query_depth' => 1]);

        $this->schema = /** @lang GraphQL */ '
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

    public function testDisableIntrospectionThroughConfig(): void
    {
        config(['lighthouse.security.disable_introspection' => DisableIntrospection::ENABLED]);

        $this->assertIntrospectionIsDisabled();
    }

    protected function assertMaxQueryComplexityIs1(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                name
            }
        }
        ')->assertGraphQLErrorMessage(
            QueryComplexity::maxQueryComplexityErrorMessage(1, 2),
        );
    }

    protected function assertMaxQueryDepthIs1(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                user {
                    user {
                        name
                    }
                }
            }
        }
        ')->assertGraphQLErrorMessage(
            QueryDepth::maxQueryDepthErrorMessage(1, 2),
        );
    }

    protected function assertIntrospectionIsDisabled(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        {
            __schema {
                queryType {
                    name
                }
            }
        }
        ')->assertGraphQLErrorMessage(
            DisableIntrospection::introspectionDisabledMessage(),
        );
    }
}
