<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

// Required by \Nuwave\Lighthouse\Schema\AST constructor when PHPStan analyzes code
config(['lighthouse.schema_cache.enable' => false]);
