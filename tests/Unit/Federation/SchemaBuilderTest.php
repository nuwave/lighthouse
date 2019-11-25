<?php

namespace Tests\Unit\Federation;

use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Schema;
use Nuwave\Lighthouse\Exceptions\FederationException;
use Nuwave\Lighthouse\Federation\FederationServiceProvider;
use Tests\TestCase;

class SchemaBuilderTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getPackageProviders($app)
    {
        return array_merge(
            parent::getPackageProviders($app),
            [FederationServiceProvider::class]
        );
    }

    protected function buildSchemaWithPlaceholderTypeAndQuery(string $schema): Schema
    {
        return $this->buildSchema(/* @lang GraphQL */<<<'GQL'
type Foo @key(fields: "id") {
    id: ID!
    foo: String!
}
type Query {
    foo: Int
}
GQL
        );
    }

    public function testGeneratesValidSchema(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery('
        type Test @key(fields: "foo") {
            foo: String!
        }
        ');

        $this->assertInstanceOf(Schema::class, $schema);
        // This would throw if the schema were invalid
        $schema->assertValid();
    }

    /**
     * At least one type needs to be defined with the @key directive!
     *
     * We could also just don't add the type definition below if no entities match. So the user is responsible
     * by himself to ad the _Entity union. In this case GraphQL itself will throw an exception if the union is missing.
     */
    public function testExpectsFederationException(): void
    {
        $this->expectException(FederationException::class);

        $this->buildSchemaWithPlaceholderQuery('');
    }

    public function testFederatedSchema(): void
    {
        $schema = $this->buildSchemaWithPlaceholderTypeAndQuery('');

        // Check if directives are returned within the schema
        $this->assertInstanceOf(Directive::class, $schema->getDirective('extends'));
        $this->assertInstanceOf(Directive::class, $schema->getDirective('external'));
        $this->assertInstanceOf(Directive::class, $schema->getDirective('key'));
        $this->assertInstanceOf(Directive::class, $schema->getDirective('provides'));
        $this->assertInstanceOf(Directive::class, $schema->getDirective('requires'));

        // Check for required federation types
        $this->assertTrue($schema->hasType('_Entity'));
        $this->assertTrue($schema->hasType('_Service'));

        // Check for existence of scalars
        $this->assertTrue($schema->hasType('_Any'));
        $this->assertTrue($schema->hasType('_FieldSet'));

        // Query type should contain federation specific fields
        $this->assertTrue($schema->getQueryType()->hasField('_entities'));
        $this->assertTrue($schema->getQueryType()->hasField('_service'));

        $entityDef = $schema->getQueryType()->getField('_entities');
        $this->assertEquals(1, count($entityDef->args));
        $this->assertInstanceOf(FieldArgument::class, $entityDef->getArg('representations'));

        $serviceDef = $schema->getQueryType()->getField('_service');
        $this->assertEmpty(count($serviceDef->args));
    }
}
