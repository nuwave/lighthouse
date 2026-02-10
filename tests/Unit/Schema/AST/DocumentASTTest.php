<?php declare(strict_types=1);

namespace Tests\Unit\Schema\AST;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Exceptions\SchemaSyntaxErrorException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\RootType;
use Tests\TestCase;
use Tests\Utils\Models\User;

final class DocumentASTTest extends TestCase
{
    public function testParsesSimpleSchema(): void
    {
        $schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: Int
        }
        GRAPHQL;
        // calculated as hash('sha256', $schema)
        $schemaHash = '774433c158904b98b4f69eddee3424679b99a70736960a189b7d7b5923695bac';
        $documentAST = DocumentAST::fromSource($schema);

        $this->assertInstanceOf(
            ObjectTypeDefinitionNode::class,
            $documentAST->types[RootType::QUERY],
        );

        $this->assertSame($schemaHash, $documentAST->hash);
    }

    public function testThrowsOnInvalidSchema(): void
    {
        $this->expectException(SchemaSyntaxErrorException::class);
        $this->expectExceptionMessage('Syntax Error: Expected Name, found !, near: ');

        DocumentAST::fromSource(/** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            bar: Int
        }

        type Query {
            foo: Int!!
        }

        type Foo {
            bar: ID
        }
        GRAPHQL);
    }

    public function testThrowsOnUnknownModelClasses(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('Failed to find a model class Unknown in namespaces [Tests\Utils\Models, Tests\Utils\ModelsSecondary] referenced in @model on type Query.');

        DocumentAST::fromSource(/** @lang GraphQL */ <<<'GRAPHQL'
        type Query @model(class: "Unknown") {
            foo: Int!
        }
        GRAPHQL);
    }

    public function testOverwritesDefinitionWithSameName(): void
    {
        $documentAST = DocumentAST::fromSource(/** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: Int
        }
        GRAPHQL);

        $overwrite = Parser::objectTypeDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            bar: Int
        }
        GRAPHQL);

        $documentAST->types[$overwrite->name->value] = $overwrite;

        $this->assertSame(
            $overwrite,
            $documentAST->types[RootType::QUERY],
        );
    }

    public function testBeSerialized(): void
    {
        $documentAST = DocumentAST::fromSource(/** @lang GraphQL */ <<<'GRAPHQL'
        extend schema @link(url: "https://specs.apollo.dev/federation/v2.3", import: ["@key"])

        type Query @model(class: "User") {
            foo: Int
        }

        directive @foo on FIELD
        GRAPHQL);

        $reserialized = unserialize(
            serialize($documentAST),
        );
        $this->assertInstanceOf(DocumentAST::class, $reserialized);

        $queryType = $reserialized->types[RootType::QUERY];
        $this->assertInstanceOf(ObjectTypeDefinitionNode::class, $queryType);

        $this->assertArrayHasKey('foo', $reserialized->directives);

        $this->assertSame(['Query'], $reserialized->classNameToObjectTypeNames[User::class]);

        $this->assertArrayHasKey(0, $reserialized->schemaExtensions);

        $schemaExtension = $reserialized->schemaExtensions[0];
        $this->assertArrayHasKey(0, $schemaExtension->directives); // @phpstan-ignore method.impossibleType (NodeList not understood by earlier deps)

        $this->assertSame($documentAST->hash, $reserialized->hash);
    }
}
