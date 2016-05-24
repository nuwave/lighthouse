<?php

$controller = config('relay.controller');

Route::post('graphql', ['as' => 'graphql', 'uses' => $controller]);
