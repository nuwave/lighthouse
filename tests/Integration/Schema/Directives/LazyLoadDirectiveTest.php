<?php

namespace Tests\Integration\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;

class LazyLoadDirectiveTest extends DBTestCase
{
    public function testLazyLoadRequiresRelationArgument(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            foo: ID @lazyLoad
        }
        ');
    }

    public function testLazyLoadRelationArgumentMustNotBeEmptyList(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            foo: ID @lazyLoad(relations: [])
        }
        ');
    }
}
