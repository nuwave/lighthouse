<?php declare(strict_types=1);

namespace Tests\Integration\Schema;

use GraphQL\Error\InvariantViolation;
use Nuwave\Lighthouse\Schema\Validator as SchemaValidator;
use Tests\TestCase;

final class ValidatorTest extends TestCase
{
    public function testOutputTypeUsedAsInput(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(foo: Foo): Int
        }

        type Foo {
            foo: Int
        }
        ';

        $schemaValidator = $this->app->make(SchemaValidator::class);

        $this->expectExceptionObject(new InvariantViolation('The type of Query.foo(foo:) must be Input Type but got: Foo.'));
        $schemaValidator->validate();
    }
}
