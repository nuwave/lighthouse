<?php

namespace Tests\Integration;

use Tests\TestCase;
use Tests\Utils\Scalars\Email;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Illuminate\Foundation\Testing\TestResponse;

class IntrospectionTest extends TestCase
{
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
        $this->schema = '
        type Foo {
            bar: Int
        }        
        '.$this->placeholderQuery();

        $this->assertIsArray(
            $this->introspectType('Foo')
        );
        $this->assertIsArray(
            $this->introspectType('Query')
        );
        $this->assertIsArray(
            $this->introspectType('Bar')
        );
    }

    /**
     * @test
     */
    public function itFindsManuallyRegisteredTypes(): void
    {
        $this->schema = $this->placeholderQuery();
        $this->typeRegistry->register(
            new Email()
        );

        $this->assertIsArray(
            $this->introspectType('Email')
        );
    }
}
