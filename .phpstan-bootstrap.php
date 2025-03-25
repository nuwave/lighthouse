<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Container\Container;

$existingConfig = Container::getInstance()->make('config');
// Required by \Nuwave\Lighthouse\Schema\AST constructor when PHPStan analyzes code
$existingConfig->set('lighthouse.schema_cache.enable', false);
