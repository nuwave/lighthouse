<?php declare(strict_types=1);

use Nuwave\Lighthouse\Rector\RootResolverSignatureRector;
use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->ruleWithConfiguration(RootResolverSignatureRector::class, [
        'paramNames' => ['_', 'args', 'context', 'resolveInfo'],
    ]);
    $rectorConfig->phpVersion(PhpVersion::PHP_82);
    $rectorConfig->bootstrapFiles([
        __DIR__ . '/../bootstrap.php',
        __DIR__ . '/../Source/CustomContext.php',
    ]);
};
