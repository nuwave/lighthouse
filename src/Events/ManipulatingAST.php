<?php

namespace Nuwave\Lighthouse\Events;

use Nuwave\Lighthouse\Schema\AST\DocumentAST;

class ManipulatingAST
{
    /**
     * The AST that can be manipulated.
     *
     * @var \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    public $documentAST;

    /**
     * BuildingAST constructor.
     *
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST
     * @return void
     */
    public function __construct(DocumentAST &$documentAST)
    {
        $this->documentAST = $documentAST;
    }
}
