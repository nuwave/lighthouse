<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Language\AST\TypeExtensionDefinitionNode;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\Factories\ExtensionFactory;

trait CanExtendTypes
{
    /**
     * Attach extensions to types.
     *
     * @param Collection $defs
     * @param array      $types
     */
    public function attachTypeExtensions(Collection $defs, array $types)
    {
        $defs->filter(function ($def) {
            return $def instanceof TypeExtensionDefinitionNode;
        })->each(function (TypeExtensionDefinitionNode $extension) use ($types) {
            collect($types)->filter(function ($type) use ($extension) {
                return $type->name === $extension->definition->name->value;
            })->each(function ($type) use ($extension) {
                ExtensionFactory::extend($extension, $type);
            });
        });
    }

    /**
     * Attach extensions to mutations.
     *
     * @param Collection $defs
     *
     * @return array
     */
    public function getMutationExtensions(Collection $defs)
    {
        return $defs->filter(function ($def) {
            return $def instanceof TypeExtensionDefinitionNode;
        })->filter(function (TypeExtensionDefinitionNode $extension) {
            return 'Mutation' === $extension->definition->name->value;
        })->mapWithKeys(function (TypeExtensionDefinitionNode $extension) {
            return ExtensionFactory::extractFields($extension);
        })->toArray();
    }

    /**
     * Attach extensions to queries.
     *
     * @param Collection $defs
     *
     * @return array
     */
    public function getQueryExtensions(Collection $defs)
    {
        return $defs->filter(function ($def) {
            return $def instanceof TypeExtensionDefinitionNode;
        })->filter(function (TypeExtensionDefinitionNode $extension) {
            return 'Query' === $extension->definition->name->value;
        })->mapWithKeys(function (TypeExtensionDefinitionNode $extension) {
            return ExtensionFactory::extractFields($extension);
        })->toArray();
    }
}
