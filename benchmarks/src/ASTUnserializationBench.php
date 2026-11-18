<?php declare(strict_types=1);

namespace Benchmarks;

use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Tally\Attribute\Before;
use Tally\Attribute\Expected;
use Tally\Attribute\Warmup;

#[Warmup(1)]
final class ASTUnserializationBench
{
    public const SCHEMA = /** @lang GraphQL */ <<<'GRAPHQL'
    type Query {
      query1: String
      query2: String
    }

    type Mutation {
      mutation1: Int
      mutation2: Int
    }

    type Foo {
      foo1: Boolean
      foo2: Boolean
    }
    GRAPHQL;

    private string $documentNode;

    private string $documentAST;

    #[Before]
    public function prepareSchema(): void
    {
        $this->documentNode = serialize(
            Parser::parse(
                self::SCHEMA,
                ['noLocation' => true], // Ignore location since it only bloats the AST
            ),
        );

        $this->documentAST = serialize(
            DocumentAST::fromSource(self::SCHEMA),
        );
    }

    #[Expected(instructions: '182.7K', cycles: '205.5K', memory: '60.82KiB')]
    public function benchUnserializeDocumentNode(): void
    {
        unserialize($this->documentNode);
    }

    #[Expected(instructions: '66.69K', cycles: '78.37K', memory: '31.62KiB')]
    public function benchUnserializeDocumentAST(): void
    {
        unserialize($this->documentAST);
    }
}
