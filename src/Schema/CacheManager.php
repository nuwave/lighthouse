<?php

namespace Nuwave\Lighthouse\Schema;

use Closure;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Utils\AST;
use Nuwave\Lighthouse\Support\Traits\CanParseTypes;

class CacheManager
{
    use CanParseTypes;

    /**
     * Store document in cache.
     *
     * @param string $schema
     */
    public function set($schema)
    {
        $document = $this->parseSchema($schema);

        file_put_contents(
            config('lighthouse.cache'),
            "<?php\nreturn ".var_export(AST::toArray($document), true)
        );

        return $document;
    }

    /**
     * Load GraphQL schema.
     *
     * @param Closure $schema
     *
     * @return DocumentNode|string
     */
    public function get(Closure $schema)
    {
        $cacheFile = config('lighthouse.cache');

        if (! $cacheFile) {
            return $schema();
        }

        return file_exists($cacheFile)
            ? AST::fromArray(require $cacheFile)
            : $this->set($schema());
    }
}
