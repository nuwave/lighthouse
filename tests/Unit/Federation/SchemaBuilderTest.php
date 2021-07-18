<?php

namespace Tests\Unit\Federation;

use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ObjectType;
use Nuwave\Lighthouse\Exceptions\FederationException;
use Nuwave\Lighthouse\Federation\FederationServiceProvider;
use Tests\TestCase;

class SchemaBuilderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [FederationServiceProvider::class]
        );
    }

    public function testFederatedSchema(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ '
        type Foo @key(fields: "id") {
            id: ID!
            foo: String!
        }

        type Query {
            foo: Int
        }
        ');

        $this->assertTrue($schema->hasType('_Entity'));
        $this->assertTrue($schema->hasType('_Service'));

        $this->assertTrue($schema->hasType('_Any'));
        $this->assertTrue($schema->hasType('_FieldSet'));

        $queryType = $schema->getQueryType();
        $this->assertInstanceOf(ObjectType::class, $queryType);

        $this->assertTrue($queryType->hasField('_entities'));
        $this->assertTrue($queryType->hasField('_service'));
    }

    /**
     * At least one type needs to be defined with the @key directive.
     *
     * We could also just don't add the type definition below if no entities match. So the user is responsible
     * by himself to ad the _Entity union. In this case GraphQL itself will throw an exception if the union is missing.
     */
    public function testThrowsIfNoTypeHasKeyDirective(): void
    {
        $this->expectException(FederationException::class);

        $this->buildSchemaWithPlaceholderQuery();
    }
}
