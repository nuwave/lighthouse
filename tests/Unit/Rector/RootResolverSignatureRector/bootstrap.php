<?php declare(strict_types=1);

use function Safe\define;

if (defined('LIGHTHOUSE_RECTOR_BOOTSTRAP_LOADED')) {
    return;
}

define('LIGHTHOUSE_RECTOR_BOOTSTRAP_LOADED', true);

require_once __DIR__ . '/../../../../vendor/larastan/larastan/bootstrap.php';

app()->make('config')->set('lighthouse', require __DIR__ . '/../../../../src/lighthouse.php');
