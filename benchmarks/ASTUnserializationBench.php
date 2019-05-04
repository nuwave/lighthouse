<?php

use GraphQL\Language\Parser;

/**
 * @BeforeMethods({"prepareSchema"})
 */
class ASTUnserializationBench
{
    const SCHEMA = /* @lang GraphQL */
        <<<'SCHEMA'
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
SCHEMA;

    /**
     * @var string
     */
    private $documentNode;

    /**
     * @var string
     */
    private $documentAST;

    public function prepareSchema(): void
    {
        $this->documentNode = serialize(
            Parser::parse(
                self::SCHEMA,
                // Ignore location since it only bloats the AST
                ['noLocation' => true]
            )
        );

        $this->documentAST = serialize(
            \Nuwave\Lighthouse\Schema\AST\DocumentAST::fromSource(self::SCHEMA)
        );
    }

    /**
     * @Revs(100)
     * @Iterations(10)
     */
    public function benchUnserializeDocumentNode(): void
    {
        unserialize($this->documentNode);
    }

    /**
     * @Revs(100)
     * @Iterations(10)
     */
    public function benchUnserializeDocumentAST(): void
    {
        unserialize($this->documentAST);
    }
}
