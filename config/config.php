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
    | Additional configuration for the route group.
    | Check options here https://lumen.laravel.com/docs/routing#route-groups
    |
    */
    'route' => [
        'prefix' => '',
        // 'middleware' => ['web','api'],    // [ 'loghttp']
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
        'register' => base_path('routes/graphql/schema.graphql'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema Cache
    |--------------------------------------------------------------------------
    |
    | A large part of the Schema generation is parsing into an AST.
    | This operation is pretty expensive so it is recommended to enable
    | caching in production mode.
    |
    */
    'cache' => [
        'enable' => env('LIGHTHOUSE_CACHE_ENABLE', false),
        'key' => env('LIGHTHOUSE_CACHE_KEY', 'lighthouse-schema'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Directives
    |--------------------------------------------------------------------------
    |
    | List directories that will be scanned for custom server-side directives.
    |
    */
    'directives' => [__DIR__.'/../app/Http/GraphQL/Directives'],

    /*
    |--------------------------------------------------------------------------
    | Namespaces
    |--------------------------------------------------------------------------
    |
    | These are the default namespaces where Lighthouse looks for classes
    | that extend functionality of the schema.
    |
    */
    'namespaces' => [
        'models' => 'App\\Models',
        'mutations' => 'App\\Http\\GraphQL\\Mutations',
        'queries' => 'App\\Http\\GraphQL\\Queries',
        'scalars' => 'App\\Http\\GraphQL\\Scalars',
        'unions' => 'App\\Http\\GraphQL\\Unions',
        'interfaces' => 'App\\Http\\GraphQL\\Interfaces',
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
    | Register error handlers that receive the Errors that occur during execution and
    | handle them. You may use this to log, filter or format the errors.
    | The classes must implement Nuwave\Lighthouse\Execution\ErrorHandler
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
        // \Nuwave\Lighthouse\Schema\Extensions\TracingExtension::class
    ],

     /*
     |--------------------------------------------------------------------------
     | GraphQL Controller
     |--------------------------------------------------------------------------
     |
     | Specify which controller (and method) you want to handle GraphQL requests.
     |
     */
    'controller' => 'Nuwave\Lighthouse\Support\Http\Controllers\GraphQLController@query',

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
];
