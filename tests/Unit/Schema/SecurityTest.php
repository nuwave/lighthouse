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
        $result = $this->query('
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
        $result = $this->query('
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
        $result = $this->query('
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
