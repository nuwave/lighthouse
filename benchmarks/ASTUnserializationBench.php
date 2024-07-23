<?php declare(strict_types=1);

namespace Benchmarks;

use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

/** @BeforeMethods({"prepareSchema"}) */
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

    protected string $documentNode;

    protected string $documentAST;

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

    /**
     * @Revs(100)
     *
     * @Iterations(10)
     */
    public function benchUnserializeDocumentNode(): void
    {
        unserialize($this->documentNode);
    }

    /**
     * @Revs(100)
     *
     * @Iterations(10)
     */
    public function benchUnserializeDocumentAST(): void
    {
        unserialize($this->documentAST);
    }
}
