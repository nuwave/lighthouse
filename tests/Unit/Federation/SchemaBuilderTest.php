<?php declare(strict_types=1);

namespace Tests\Unit\Federation;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use Nuwave\Lighthouse\Federation\FederationException;
use Nuwave\Lighthouse\Federation\FederationServiceProvider;
use Tests\TestCase;

final class SchemaBuilderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [FederationServiceProvider::class],
        );
    }

    public function testFederatedSchema(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        type Foo @key(fields: "id") {
            id: ID!
            foo: String!
        }

        type Query {
            foo: Int
        }
        GRAPHQL);

        $this->assertTrue($schema->hasType('_Entity'));
        $this->assertTrue($schema->hasType('_Service'));

        $this->assertTrue($schema->hasType('_Any'));
        $this->assertTrue($schema->hasType('_FieldSet'));

        $this->assertSchemaHasQueryTypeWithFederationFields($schema);
    }

    public function testAddsQueryTypeIfNotDefined(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        type Foo @key(fields: "id") {
            id: ID!
            foo: String!
        }
        GRAPHQL);

        $this->assertSchemaHasQueryTypeWithFederationFields($schema);
    }

    private function assertSchemaHasQueryTypeWithFederationFields(Schema $schema): void
    {
        $queryType = $schema->getQueryType();
        $this->assertInstanceOf(ObjectType::class, $queryType);

        $this->assertTrue($queryType->hasField('_entities'));
        $this->assertTrue($queryType->hasField('_service'));
    }

    /**
     * At least one type needs to be defined with the @key directive.
     *
     * We could also just not add the type definition below if no entities match,
     * so the user themselves is responsible to add the _Entity union.
     * In this case, GraphQL validation will throw an exception if the union is missing.
     */
    public function testThrowsIfNoTypeHasKeyDirective(): void
    {
        $this->expectException(FederationException::class);

        $this->buildSchemaWithPlaceholderQuery('');
    }
}
