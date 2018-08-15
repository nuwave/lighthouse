<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use Illuminate\Support\Collection;

class ExtensionRegistry
{
    /**
     * @var \Illuminate\Support\Collection
     */
    protected $extensions;

    /**
     * Create instance of extension registry.
     */
    public function __construct()
    {
        $this->extensions = collect();
    }

    /**
     * Register graphql extension.
     *
     * @param GraphQLExtension $extension
     */
    public function register(GraphQLExtension $extension): ExtensionRegistry
    {
        $this->extensions->put($extension->name(), $extension);

        return $this;
    }

    /**
     * Register graphql extensions.
     *
     * @param array $extensions
     */
    public function registerMany(array $extensions): ExtensionRegistry
    {
        foreach ($extensions as $extension) {
            $this->register($extension);
        }

        return $this;
    }

    /**
     * Get extension.
     *
     * @param name $name
     *
     * @return GraphQLExtension|null
     */
    public function get(string $name)
    {
        return $this->extensions->get($name);
    }

    /**
     * Get active extensions.
     *
     * @return Collection
     */
    public function active(): Collection
    {
        $extensions = config('lighthouse.extensions', []);

        if (is_string($extensions)) {
            $extensions = explode(',', $extensions);
        }

        return $this->extensions->only($extensions);
    }

    /**
     * Handle request start.
     *
     * @param ExtensionRequest $request
     */
    public function requestDidStart(ExtensionRequest $request): ExtensionRegistry
    {
        $this->active()->each(function (GraphQLExtension $extension) use ($request) {
            $extension->requestDidStart($request);
        });

        return $this;
    }

    /**
     * Get output for all extensions.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->active()
            ->mapWithKeys(function (GraphQLExtension $extension) {
                return [$extension->name() => $extension->toArray()];
            })
            ->toArray();
    }
}
