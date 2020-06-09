<?php

namespace Nuwave\Lighthouse\Events;

use Nuwave\Lighthouse\Schema\AST\DocumentAST;

/**
 * Fires after the AST was built but before the executable schema is built.
 *
 * Listeners may mutate the $documentAST and make programmatic
 * changes to the schema.
 *
 * Only fires once if schema caching is active.
 */
class ManipulateAST
{
    /**
     * The AST that can be manipulated.
     *
     * @var \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    public $documentAST;

    public function __construct(DocumentAST &$documentAST)
    {
        $this->documentAST = $documentAST;
    }
}
