<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Schema\Resolvers\EnumResolver;
use Nuwave\Lighthouse\Schema\Resolvers\InterfaceResolver;
use Nuwave\Lighthouse\Schema\Resolvers\ScalarResolver;

class SchemaBuilder
{
    /**
     * Collection of schema enums.
     *
     * @var array
     */
    protected $enums = [];

    /**
     * Collection of schema scalars.
     *
     * @var array
     */
    protected $scalars = [];

    /**
     * Collection of schema interfaces.
     *
     * @var array
     */
    protected $interfaces = [];

    /**
     * Collection of schema types.
     *
     * @var array
     */
    protected $types = [];

    /**
     * Collection of schema input.
     *
     * @var array
     */
    protected $input = [];

    /**
     * Collection of schema unions.
     *
     * @var array
     */
    protected $unions = [];

    /**
     * Collection of schema queries.
     *
     * @var array
     */
    protected $queries = [];

    /**
     * Collection of schema mutations.
     *
     * @var array
     */
    protected $mutations = [];

    /**
     * Document node to parse.
     *
     * @var DocumentNode
     */
    protected $document;

    /**
     * Generate a GraphQL Schema.
     *
     * @param string $schema
     *
     * @return mixed
     */
    public function build($schema)
    {
        // ...
    }

    /**
     * Parse schema definitions.
     *
     * @param string $schema
     *
     * @return \Illuminate\Support\Collection
     */
    public function register($schema)
    {
        $this->document = Parser::parse($schema);

        $this->setTypes();

        return collect(array_merge(
            $this->enums,
            $this->interfaces,
            $this->scalars
        ));
    }

    /**
     * Set schema types.
     */
    protected function setTypes()
    {
        $this->setEnums();
        $this->setInterfaces();
        $this->setScalars();
    }

    /**
     * Set enum types.
     */
    protected function setEnums()
    {
        $this->enums = collect($this->document->definitions)
            ->filter(function ($def) {
                return $def instanceof EnumTypeDefinitionNode;
            })->map(function (EnumTypeDefinitionNode $enum) {
                return EnumResolver::resolve($enum);
            })->toArray();
    }

    /**
     * Set interface types.
     */
    protected function setInterfaces()
    {
        $this->interfaces = collect($this->document->definitions)
            ->filter(function ($def) {
                return $def instanceof InterfaceTypeDefinitionNode;
            })->map(function (InterfaceTypeDefinitionNode $interface) {
                return InterfaceResolver::resolve($interface);
            })->toArray();
    }

    /**
     * Set scalar types.
     */
    protected function setScalars()
    {
        $this->scalars = collect($this->document->definitions)
            ->filter(function ($def) {
                return $def instanceof ScalarTypeDefinitionNode;
            })->map(function (ScalarTypeDefinitionNode $scalar) {
                return count($scalar->directives)
                    ? directives()->forNode($scalar)
                    : ScalarResolver::resolver($scalar);
            })->toArray();
    }
}
