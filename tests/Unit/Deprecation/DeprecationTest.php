<?php

namespace Tests\Unit\Deprecation;

use GraphQL\Validator\DocumentValidator;
use Nuwave\Lighthouse\Deprecation\DeprecationValidationRule;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;

class DeprecationTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // TODO @see ValidationRulesProvider
        DocumentValidator::addRule(new DeprecationValidationRule());
    }

    public function testDetectsDeprecatedFields(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @deprecated
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER
            ]
        ]);
    }

    public function testDetectsDeprecatedEnumValueUsage(): void
    {
        $this->schema = /** @lang GraphQL */ '
        enum Foo {
            A
            B @deprecated
        }

        type Query {
            foo(foo: Foo): Int
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(foo: B)
        }
        ')->assertExactJson([
            'data' => [
                'foo' => Foo::THE_ANSWER
            ]
        ]);
    }
}
