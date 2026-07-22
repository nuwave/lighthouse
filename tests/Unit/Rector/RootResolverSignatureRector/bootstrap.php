<?php declare(strict_types=1);

use function Safe\define;

if (defined('LIGHTHOUSE_RECTOR_BOOTSTRAP_LOADED')) {
    return;
}

define('LIGHTHOUSE_RECTOR_BOOTSTRAP_LOADED', true);

$errorHandler = set_error_handler(static fn () => false);
restore_error_handler();
$exceptionHandler = set_exception_handler(static fn () => null);
restore_exception_handler();

require_once __DIR__ . '/../../../../vendor/larastan/larastan/bootstrap.php';

// Undo handlers registered by Laravel's HandleExceptions bootstrapper
// to avoid PHPUnit risky test warnings about leaked handlers.
while (set_error_handler(static fn () => false) !== $errorHandler) {
    restore_error_handler();
    restore_error_handler();
}
restore_error_handler();

while (set_exception_handler(static fn () => null) !== $exceptionHandler) {
    restore_exception_handler();
    restore_exception_handler();
}
restore_exception_handler();

config()->set('lighthouse', require __DIR__ . '/../../../../src/lighthouse.php');
