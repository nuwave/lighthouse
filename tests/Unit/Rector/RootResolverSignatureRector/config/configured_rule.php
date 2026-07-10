<?php declare(strict_types=1);

use Nuwave\Lighthouse\Rector\RootResolverSignatureRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(RootResolverSignatureRector::class);
    $rectorConfig->importNames();
    $rectorConfig->bootstrapFiles([
        __DIR__ . '/../bootstrap.php',
        __DIR__ . '/../Source/CustomContext.php',
    ]);
};
