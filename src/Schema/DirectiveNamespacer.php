<?php

namespace Nuwave\Lighthouse\Schema;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class DirectiveNamespacer
{
    /**
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $dispatcher;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * A list of namespaces with directives in descending priority.
     *
     * @return string[]
     */
    public function gather(): array
    {
        // When looking for a directive by name, the namespaces are tried in order
        return (
            new Collection([
                // User defined directives (top priority)
                config('lighthouse.namespaces.directives'),

                // Plugin developers defined directives
                $this->dispatcher->dispatch(new RegisterDirectiveNamespaces),

                // Lighthouse defined directives
                'Nuwave\\Lighthouse\\Schema\\Directives',
            ]))
            ->flatten()
            ->filter()
            ->all();
    }
}
