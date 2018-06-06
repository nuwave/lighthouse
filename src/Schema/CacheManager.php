<?php

namespace Nuwave\Lighthouse\Schema;

use Closure;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Utils\AST;

class CacheManager
{
    /**
     * Store document in cache.
     *
     * @param string $schema
     * @return DocumentNode
     */
    public function set($schema)
    {
        $document = Parser::parse($schema);

        file_put_contents(
            config('lighthouse.cache'),
            "<?php\nreturn ".var_export(AST::toArray($document), true).';',
            LOCK_EX
        );

        return $document;
    }

    /**
     * Load GraphQL schema from cache if it exists.
     *
     * @return DocumentAST|null
     */
    public function get(Closure $schema)
    {
        $cacheFile = config('lighthouse.cache');

        if (! $cacheFile) {
            return $schema();
        }

        return file_exists($cacheFile)
//            && AST::fromArray(require $cacheFile)
            : $this->set($schema());
    }
}
