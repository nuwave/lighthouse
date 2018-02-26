<?php

namespace Nuwave\Lighthouse;

use Nuwave\Lighthouse\Schema\DirectiveFactory;
use Nuwave\Lighthouse\Schema\Utils\SchemaStitcher;

class GraphQL
{
    /**
     * Directive container.
     *
     * @var DirectiveFactory
     */
    protected $directives;

    /**
     * Schema Stitcher.
     *
     * @var SchemaStitcher
     */
    protected $stitcher;

    public function buildSchema()
    {
        // 1. Register directives
        // 2. Get stitched schema
        // 3. Parse schema into documentnode
        // 4. Pass node to schema container to parse
        // 5. Build GraphQL Schema.
    }

    /**
     * Get an instance of the directive container.
     *
     * @return DirectiveFactory
     */
    public function directives()
    {
        if (! $this->directives) {
            $this->directives = app(DirectiveFactory::class);
        }

        return $this->directives;
    }

    /**
     * Get instance of schema stitcher.
     *
     * @return SchemaStitcher
     */
    public function stitcher()
    {
        if (! $this->stitcher) {
            $this->stitcher = app(SchemaStitcher::class);
        }

        return $this->stitcher;
    }
}
