<?php

namespace Tests\Integration;

use Tests\TestCase;
use Tests\Utils\Scalars\Email;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Illuminate\Foundation\Testing\TestResponse;

class IntrospectionTest extends TestCase
{
    /**
     * @see https://gist.github.com/craigbeck/b90915d49fda19d5b2b17ead14dcd6da
     */
    const INTROSPECTION_QUERY = /* @lang GraphQL */
        <<<'GRAPHQL'
  query IntrospectionQuery {
    __schema {
      queryType { name }
      mutationType { name }
      subscriptionType { name }
      types {
        ...FullType
      }
      directives {
        name
        description
        args {
          ...InputValue
        }
        locations
      }
    }
  }

  fragment FullType on __Type {
    kind
    name
    description
    fields(includeDeprecated: true) {
      name
      description
      args {
        ...InputValue
      }
      type {
        ...TypeRef
      }
      isDeprecated
      deprecationReason
    }
    inputFields {
      ...InputValue
    }
    interfaces {
      ...TypeRef
    }
    enumValues(includeDeprecated: true) {
      name
      description
      isDeprecated
      deprecationReason
    }
    possibleTypes {
      ...TypeRef
    }
  }

  fragment InputValue on __InputValue {
    name
    description
    type { ...TypeRef }
    defaultValue
  }

  fragment TypeRef on __Type {
    kind
    name
    ofType {
      kind
      name
      ofType {
        kind
        name
        ofType {
          kind
          name
        }
      }
    }
  }
GRAPHQL;

    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    /**
     * @var TestResponse|null
     */
    protected $introspectionResult;

    protected function setUp(): void
    {
        parent::setUp();

        $this->typeRegistry = $this->app->make(TypeRegistry::class);
    }

    /**
     * @test
     */
    public function itFindsTypesFromSchema(): void
    {
        $this->introspect('
        type Foo {
            bar: Int
        }        
        '.$this->placeholderQuery()
        );

        $this->assertTrue(
            $this->isTypeNamePresent('Foo')
        );
        $this->assertTrue(
            $this->isTypeNamePresent('Query')
        );
        $this->assertFalse(
            $this->isTypeNamePresent('Bar')
        );
    }

    /**
     * @test
     */
    public function itFindsManuallyRegisteredTypes(): void
    {
        $this->typeRegistry->register(
            new Email()
        );

        $this->introspect(
            $this->placeholderQuery()
        );

        $this->assertTrue(
            $this->isTypeNamePresent('Email')
        );
    }

    protected function introspect(string $schema): void
    {
        $this->schema = $schema;

        $this->introspectionResult = $this->graphQL(self::INTROSPECTION_QUERY);
    }

    protected function isTypeNamePresent(string $typeName): bool
    {
        return (new Collection($this->introspectionResult->jsonGet('data.__schema.types')))
            ->contains(function (array $type) use ($typeName): bool {
                return $type['name'] === $typeName;
            });
    }
}
