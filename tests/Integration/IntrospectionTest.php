<?php

namespace Tests\Integration;

use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Tests\TestCase;
use Tests\Utils\Scalars\Email;

class IntrospectionTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    /**
     * @var \Illuminate\Foundation\Testing\TestResponse|\Illuminate\Testing\TestResponse|null
     */
    protected $introspectionResult;

    protected function setUp(): void
    {
        parent::setUp();

        $this->typeRegistry = $this->app->make(TypeRegistry::class);
    }

    public function testFindsTypesFromSchema(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Foo {
            bar: Int
        }
        ';

        $this->assertNotNull(
            $this->introspectType('Foo')
        );
        $this->assertNotNull(
            $this->introspectType(RootType::QUERY)
        );

        $this->assertNull(
            $this->introspectType('Bar')
        );
    }

    public function testFindsManuallyRegisteredTypes(): void
    {
        $this->typeRegistry->register(
            new Email()
        );

        $this->assertNotNull(
            $this->introspectType('Email')
        );
    }
}
