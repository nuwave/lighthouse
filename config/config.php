<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LightHouse endpoint & middleware
    |--------------------------------------------------------------------------
    |
    | Setup this values as required,
    | default route endpoints is yourdomain.com/graphql
    | get requests to your graphql endpoint are disabled by default, you may enable them below
    | setup middleware here for all request,
    | setup more endpoints, ej: pointing to the controller value inside your route file
    |
    */
    'route_name' => 'graphql',

    'route_enable_get' => false,

    'route' => [
        'prefix' => '',
        // 'middleware' => ['web','api'],    // [ 'loghttp']
    ],

    /*
    |--------------------------------------------------------------------------
    | Directive registry
    |--------------------------------------------------------------------------
    |
    | This package allows you to create your own server-side directives.
    | List directories that will be scanned for custom directives.
    | Hint: Directives must implement \Nuwave\Lighthouse\Schema\Directives\Directive
    |
    */
    'directives' => [__DIR__.'/../app/Http/GraphQL/Directives'],

    /*
    |--------------------------------------------------------------------------
    | Namespace registry
    |--------------------------------------------------------------------------
    |
    | This package provides a set of commands to make it easy for you to
    | create new parts in your GraphQL schema. Change these values to
    | match the namespaces you'd like each piece to be created in.
    |
    */
    'namespaces' => [
        'models' => 'App\\Models',
        'mutations' => 'App\\Http\\GraphQL\\Mutations',
        'queries' => 'App\\Http\\GraphQL\\Queries',
        'scalars' => 'App\\Http\\GraphQL\\Scalars',
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
    | Global ID
    |--------------------------------------------------------------------------
    |
    | When creating a GraphQL type that is Relay compliant, provide a named field
    | for the Node identifier.
    |
    */
    'global_id_field' => '_id',

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
];
