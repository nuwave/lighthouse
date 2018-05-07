<?php

namespace Nuwave\Lighthouse\Schema\Resolvers;

use Closure;
use GraphQL\Language\AST\FieldDefinitionNode;

abstract class FieldResolver
{
    /**
     * Instance of field to resolve.
     *
     * @var FieldDefinitionNode
     */
    protected $field;

    /**
     * Field resolver.
     *
     * @var Closure
     */
    protected $resolver;

    /**
     * Create a new instance of field resolver.
     *
     * @param FieldDefinitionNode $field
     * @param Closure|null        $resolver
     */
    public function __construct(FieldDefinitionNode $field, Closure $resolver = null)
    {
        $this->field = $field;
        $this->resolver = $resolver;
    }

    /**
     * Resolve field type from field.
     *
     * @param FieldDefinitionNode $field
     * @param Closure|null        $resolver
     *
     * @return array
     */
    public static function resolve(FieldDefinitionNode $field, Closure $resolver = null)
    {
        $instance = new static($field, $resolver);

        return $instance->generate();
    }

    /**
     * Generate a GraphQL type from a field.
     *
     * @return array
     */
    abstract public function generate();
}
