<?php

use GraphQL\Error\Debug;
use GraphQL\Validator\Rules\DisableIntrospection;

return [

    /*
    |--------------------------------------------------------------------------
    | GraphQL endpoint
    |--------------------------------------------------------------------------
    |
    | Set the endpoint to which the GraphQL server responds.
    | The default route endpoint is "yourdomain.com/graphql".
    |
    */

    'route_name' => 'graphql',

    /*
    |--------------------------------------------------------------------------
    | Enable GET requests
    |--------------------------------------------------------------------------
    |
    | This setting controls if GET requests to the GraphQL endpoint are allowed.
    |
    */

    'route_enable_get' => true,

    /*
    |--------------------------------------------------------------------------
    | Route configuration
    |--------------------------------------------------------------------------
    |
    | Additional configuration for the route group https://lumen.laravel.com/docs/routing#route-groups
    |
    | Beware that middleware defined here runs before the GraphQL execution phase.
    | This means that errors will cause the whole query to abort and return a
    | response that is not spec-compliant. It is preferable to use directives
    | to add middleware to single fields in the schema.
    | Read more https://lighthouse-php.com/docs/auth.html#apply-auth-middleware
    |
    */

    'route' => [
        'prefix' => '',
        // 'middleware' => ['loghttp']
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema declaration
    |--------------------------------------------------------------------------
    |
    | This is a path that points to where your GraphQL schema is located
    | relative to the app path. You should define your entire GraphQL
    | schema in this file (additional files may be imported).
    |
    */

    'schema' => [
        'register' => base_path('graphql/schema.graphql'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema Cache
    |--------------------------------------------------------------------------
    |
    | A large part of schema generation is parsing the schema into an AST.
    | This operation is pretty expensive so it is recommended to enable
    | caching in production mode, especially for large schemas.
    |
    */

    'cache' => [
        'enable' => env('LIGHTHOUSE_CACHE_ENABLE', false),
        'key' => env('LIGHTHOUSE_CACHE_KEY', 'lighthouse-schema'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Namespaces
    |--------------------------------------------------------------------------
    |
    | These are the default namespaces where Lighthouse looks for classes
    | that extend functionality of the schema. You may pass either a string
    | or an array, they are tried in order and the first match is used.
    |
    */

    'namespaces' => [
        'models' => ['App', 'App\\Models'],
        'queries' => 'App\\GraphQL\\Queries',
        'mutations' => 'App\\GraphQL\\Mutations',
        'subscriptions' => 'App\\GraphQL\\Subscriptions',
        'interfaces' => 'App\\GraphQL\\Interfaces',
        'unions' => 'App\\GraphQL\\Unions',
        'scalars' => 'App\\GraphQL\\Scalars',
        'directives' => ['App\\GraphQL\\Directives'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | Control how Lighthouse handles security related query validation.
    | This configures the options from http://webonyx.github.io/graphql-php/security/
    | A setting of "0" means that the validation rule is disabled.
    |
    */

    'security' => [
        'max_query_complexity' => 0,
        'max_query_depth' => 0,
        'disable_introspection' => DisableIntrospection::DISABLED,
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug
    |--------------------------------------------------------------------------
    |
    | Control the debug level as described in http://webonyx.github.io/graphql-php/error-handling/
    | Debugging is only applied if the global Laravel debug config is set to true.
    |
    */

    'debug' => Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE,

    /*
    |--------------------------------------------------------------------------
    | Error Handlers
    |--------------------------------------------------------------------------
    |
    | Register error handlers that receive the Errors that occur during execution
    | and handle them. You may use this to log, filter or format the errors.
    | The classes must implement \Nuwave\Lighthouse\Execution\ErrorHandler
    |
    */

    'error_handlers' => [
        \Nuwave\Lighthouse\Execution\ExtensionErrorHandler::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Extensions
    |--------------------------------------------------------------------------
    |
    | Register extension classes that extend \Nuwave\Lighthouse\Schema\Extensions\GraphQLExtension
    |
    */

    'extensions' => [
        // \Nuwave\Lighthouse\Schema\Extensions\DeferExtension::class,
        // \Nuwave\Lighthouse\Schema\Extensions\SubscriptionExtension::class,
        // \Nuwave\Lighthouse\Schema\Extensions\TracingExtension::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | GraphQL Controller
    |--------------------------------------------------------------------------
    |
    | Specify which controller (and method) you want to handle GraphQL requests.
    |
    */

    'controller' => \Nuwave\Lighthouse\Support\Http\Controllers\GraphQLController::class.'@query',

    /*
    |--------------------------------------------------------------------------
    | Global ID
    |--------------------------------------------------------------------------
    |
    | The name that is used for the global id field on the Node interface.
    | When creating a Relay compliant server, this must be named "id".
    |
    */

    'global_id_field' => 'id',

    /*
    |--------------------------------------------------------------------------
    | Batched Queries
    |--------------------------------------------------------------------------
    |
    | GraphQL query batching means sending multiple queries to the server in one request,
    | You may set this flag to either process or deny batched queries.
    |
    */

    'batched_queries' => true,

    /*
    |--------------------------------------------------------------------------
    | Transactional Mutations
    |--------------------------------------------------------------------------
    |
    | Sets default setting for transactional mutations.
    | You may set this flag to have @create|@update mutations transactional or not.
    |
    */

    'transactional_mutations' => true,

    /*
    |--------------------------------------------------------------------------
    | GraphQL Subscriptions
    |--------------------------------------------------------------------------
    |
    | Here you can define GraphQL subscription "broadcasters" and "storage" drivers
    | as well their required configuration options.
    |
    */

    'subscriptions' => [
        /*
         * Determines if broadcasts should be queued by default.
         */
        'queue_broadcasts' => env('LIGHTHOUSE_QUEUE_BROADCASTS', true),

        /*
         * Default subscription storage.
         *
         * Any Laravel supported cache driver options are available here.
         */
        'storage' => env('LIGHTHOUSE_SUBSCRIPTION_STORAGE', 'redis'),

        /*
         * Default subscription broadcaster.
         */
        'broadcaster' => env('LIGHTHOUSE_BROADCASTER', 'pusher'),

        /*
         * Subscription broadcasting drivers with config options.
         */
        'broadcasters' => [
            'log' => [
                'driver' => 'log',
            ],
            'pusher' => [
                'driver' => 'pusher',
                'routes' => \Nuwave\Lighthouse\Subscriptions\SubscriptionRouter::class.'@pusher',
                'connection' => 'pusher',
            ],
        ],
    ],

];
