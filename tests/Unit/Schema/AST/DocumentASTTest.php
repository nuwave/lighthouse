<?php declare(strict_types=1);

namespace Tests\Unit\Schema\AST;

use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\SchemaExtensionNode;
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
        $schema = /** @lang GraphQL */ '
        type Query {
            foo: Int
        }
        ';
        // calculated as hash('sha256', $schema)
        $schemaHash = '99fd7bd3f58a98d8932c1f5d1da718707f6f471e93d96e0bc913436445a947ac';
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

        DocumentAST::fromSource(/** @lang GraphQL */ '
        type Mutation {
            bar: Int
        }

        type Query {
            foo: Int!!
        }

        type Foo {
            bar: ID
        }
        ');
    }

    public function testThrowsOnUnknownModelClasses(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('Failed to find a model class Unknown in namespaces [Tests\Utils\Models, Tests\Utils\ModelsSecondary] referenced in @model on type Query.');

        DocumentAST::fromSource(/** @lang GraphQL */ '
        type Query @model(class: "Unknown") {
            foo: Int!
        }
        ');
    }

    public function testOverwritesDefinitionWithSameName(): void
    {
        $documentAST = DocumentAST::fromSource(/** @lang GraphQL */ '
        type Query {
            foo: Int
        }
        ');

        $overwrite = Parser::objectTypeDefinition(/** @lang GraphQL */ '
        type Query {
            bar: Int
        }
        ');

        $documentAST->types[$overwrite->name->value] = $overwrite;

        $this->assertSame(
            $overwrite,
            $documentAST->types[RootType::QUERY],
        );
    }

    public function testBeSerialized(): void
    {
        $documentAST = DocumentAST::fromSource(/** @lang GraphQL */ '
        extend schema @link(url: "https://specs.apollo.dev/federation/v2.3", import: ["@key"])

        type Query @model(class: "User") {
            foo: Int
        }

        directive @foo on FIELD
        ');

        $reserialized = unserialize(
            serialize($documentAST),
        );
        assert($reserialized instanceof DocumentAST);

        $queryType = $reserialized->types[RootType::QUERY];
        $this->assertInstanceOf(ObjectTypeDefinitionNode::class, $queryType);

        $this->assertInstanceOf(DirectiveDefinitionNode::class, $reserialized->directives['foo']);

        $this->assertSame(['Query'], $reserialized->classNameToObjectTypeNames[User::class]);

        $schemaExtension = $reserialized->schemaExtensions[0];
        $this->assertInstanceOf(SchemaExtensionNode::class, $schemaExtension);
        $this->assertInstanceOf(DirectiveNode::class, $schemaExtension->directives[0]);

        $this->assertSame($documentAST->hash, $reserialized->hash);
    }
}
