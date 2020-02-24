<?php

namespace Nuwave\Lighthouse\Validation;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Validation\Validator;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class ValidationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @param  \Illuminate\Validation\Factory  $validationFactory
     * @param  \Illuminate\Contracts\Events\Dispatcher  $dispatcher
     * @return void
     */
    public function boot(ValidationFactory $validationFactory, Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            function (RegisterDirectiveNamespaces $registerDirectiveNamespaces): string {
                return __NAMESPACE__;
            }
        );

        $validationFactory->resolver(
            function ($translator, array $data, array $rules, array $messages, array $customAttributes): Validator {
                // This determines whether we are resolving a GraphQL field
                return Arr::has($customAttributes, ['root', 'context', 'resolveInfo'])
                    ? new GraphQLValidator($translator, $data, $rules, $messages, $customAttributes)
                    : new Validator($translator, $data, $rules, $messages, $customAttributes);
            }
        );
    }
}
