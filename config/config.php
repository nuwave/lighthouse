<?php

return [
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

    // 'namespaces' => [
    //     'mutations' => 'App\\GraphQL\\Http\\Mutations',
    //     'queries'   => 'App\\GraphQL\\Http\\Queries',
    //     'types'     => 'App\\GraphQL\\Http\\Types',
    //     'fields'    => 'App\\GraphQL\\Http\\Fields',
    // ],

    'cache' => storage_path('graphql/cache'),
    'controller' => 'Nuwave\Relay\Support\Http\Controllers\LaravelController@query',
    // 'model_path' => 'App\\Models',
    // 'camel_case' => false,

    'globalId' => [
        'encode' => null,
        'decodeId' => null,
        'decodeType' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema declaration
    |--------------------------------------------------------------------------
    |
    | This is a path that points to where your Relay schema is located
    | relative to the app path. You should define your entire Relay
    | schema in this file. Declare any Relay queries, mutations,
    | and types here instead of laravel-graphql config file.
    |
    */

    'schema' => [
        // 'output' => null,
        'register' => function () {
            //
        }
    ],
];
