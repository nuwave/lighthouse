<?php

use Tests\Utils\Models\Location;

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Location::class, function (): array {
    return [
        'parent_id' => null,
    ];
});
