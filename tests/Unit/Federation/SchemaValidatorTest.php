<?php

namespace Tests\Unit\Federation;

use Nuwave\Lighthouse\Console\ValidateSchemaCommand;
use Nuwave\Lighthouse\Events\ValidateSchema;
use Nuwave\Lighthouse\Exceptions\FederationException;
use Nuwave\Lighthouse\Federation\FederationServiceProvider;
use Nuwave\Lighthouse\Federation\SchemaValidator;
use Tests\TestCase;

class SchemaValidatorTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [FederationServiceProvider::class]
        );
    }

    public function testHooksIntoValidateSchemaCommand(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Foo @key(fields: "not_defined_on_the_object_type") {
          id: ID!
        }
        '.self::PLACEHOLDER_QUERY;
        $tester = $this->commandTester(new ValidateSchemaCommand());

        $this->expectException(FederationException::class);
        $tester->execute([]);
    }

    public function testValidatesSuccessfully(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type Foo @key(fields: "id") {
          id: ID!
        }
        ');

        /** @var \Nuwave\Lighthouse\Federation\SchemaValidator $validator */
        $validator = app(SchemaValidator::class);

        $validator->handle(new ValidateSchema($schema));
        $this->assertTrue(true);
    }

    public function testValidatesUsesFieldNodes(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type Foo @key(fields: "...{ id }") {
          id: ID!
        }
        ');

        /** @var \Nuwave\Lighthouse\Federation\SchemaValidator $validator */
        $validator = app(SchemaValidator::class);

        $this->expectException(FederationException::class);
        $validator->handle(new ValidateSchema($schema));
    }

    public function testValidatesMissingExternalDirective(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type Foo @key(fields: "id") @extends {
          id: ID! @mock
        }
        ');

        /** @var \Nuwave\Lighthouse\Federation\SchemaValidator $validator */
        $validator = app(SchemaValidator::class);

        $this->expectException(FederationException::class);
        $validator->handle(new ValidateSchema($schema));
    }

    public function testValidatesNestedSuccessfully(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type Foo @key(fields: "id bar { id }") {
          id: ID!
          bar: Bar!
        }

        type Bar {
          id: ID!
        }
        ');

        /** @var \Nuwave\Lighthouse\Federation\SchemaValidator $validator */
        $validator = app(SchemaValidator::class);

        $validator->handle(new ValidateSchema($schema));
        $this->assertTrue(true);
    }

    public function testValidatesNestedMissingExternal(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type Foo @key(fields: "id foo { id }") @extends {
          id: ID! @external
          bar: Bar! @external
        }

        type Bar {
          id: ID!
        }
        ');

        /** @var \Nuwave\Lighthouse\Federation\SchemaValidator $validator */
        $validator = app(SchemaValidator::class);

        $this->expectException(FederationException::class);
        $validator->handle(new ValidateSchema($schema));
    }
}
