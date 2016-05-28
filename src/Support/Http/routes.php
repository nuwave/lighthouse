<?php

$controller = config('lighthouse.controller');

Route::post('graphql', ['as' => 'graphql', 'uses' => $controller]);
