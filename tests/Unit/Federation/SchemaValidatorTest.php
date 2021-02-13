<?php

namespace Tests\Unit\Federation;

use Nuwave\Lighthouse\Console\ValidateSchemaCommand;
use Nuwave\Lighthouse\Exceptions\FederationException;
use Nuwave\Lighthouse\Federation\FederationServiceProvider;
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
          id: ID! @external
        }

        type Query {
          foo: Int!
        }
        ';
        $tester = $this->commandTester(new ValidateSchemaCommand());

        $this->expectException(FederationException::class);
        $tester->execute([]);
    }
}
