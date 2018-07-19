<?php

namespace Tests\Unit\Schema\Directives\Nodes;

use GraphQL\Type\Definition\ScalarType;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Tests\TestCase;

class ScalarDirectiveTest extends TestCase
{
    /**
     * Get test environment setup.
     *
     * @param mixed $app
     */
    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set(
            'lighthouse.namespaces.scalars',
            'Nuwave\Lighthouse\Schema\Types\Scalars'
        );
    }

    /**
     * @test
     */
    public function itCanResolveScalarTypes()
    {
        $schema = $this->buildSchemaWithDefaultQuery('
        scalar DateTime @scalar(class: "DateTime")
        ');

        $this->assertInstanceOf(ScalarType::class, $schema->getType('DateTime'));
    }

    /**
     * @test
     */
    public function itFallsBackToTypeNameIfNoClassIsGiven()
    {
        $schema = $this->buildSchemaWithDefaultQuery('
        scalar DateTime @scalar
        ');

        $this->assertInstanceOf(ScalarType::class, $schema->getType('DateTime'));
    }

    /**
     * @test
     */
    public function itResolvesScalarWithoutDirective()
    {
        $schema = $this->buildSchemaWithDefaultQuery('
        scalar DateTime
        ');

        $this->assertInstanceOf(ScalarType::class, $schema->getType('DateTime'));
    }

    /**
     * @test
     */
    public function itThrowsIfNoClassIsFound()
    {
        $this->expectException(DirectiveException::class);

        $schema = $this->buildSchemaWithDefaultQuery('
        scalar WhatEver
        ');
    }

    /**
     * @test
     */
    public function itThrowsIfNoClassIsFoundEvenIfDefinitionIsRight()
    {
        $this->expectException(DirectiveException::class);

        $schema = $this->buildSchemaWithDefaultQuery('
        scalar DateTime @scalar(class: "WhatEver")
        ');
    }
}
