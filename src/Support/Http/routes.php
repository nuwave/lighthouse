<?php

resolve('router')->group(config('lighthouse.route', []), function (){
    $routeName = config('lighthouse.route_name', 'graphql');
    $controller = config('lighthouse.controller');

    if (config('lighthouse.route_enable_get', false)) {
        resolve('router')->get($routeName,
          ['as' => 'lighthouse.graphql', 'uses' => $controller]
        );
    }

    resolve('router')->post($routeName,
      ['as' => 'lighthouse.graphql', 'uses' => $controller]
    );
});
